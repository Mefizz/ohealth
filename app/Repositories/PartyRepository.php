<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Status;
use App\Enums\User\Role;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use App\Models\Relations\Party;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PartyRepository
{
    /**
     * Sync employee↔user pivot and Spatie roles for users of this party in the legal entity.
     *
     * Roles and scopes must come only from employees bound to that user (user_id, pivot,
     * or employee_request email) — never from every APPROVED employee on the party.
     * eHealth oauth/apps/authorize rejects the whole request when local roles invent scopes
     * the account does not hold (RBAC 422), which locks the user out of login.
     */
    public function syncUserEmployeesAndRoles(Party $party, LegalEntity $legalEntity): void
    {
        $isLegalEntityCanBeReorganized = $legalEntity->type->name === LegalEntity::TYPE_PRIMARY_CARE
            || $legalEntity->type->name === LegalEntity::TYPE_OUTPATIENT;

        $partyEmployees = Employee::getEmployeesForParty(legalEntityId: $legalEntity->id, partyId: $party->id)->get();

        if ($legalEntity->status === Status::REORGANIZED->value) {
            $reorganizedEmployees = Employee::getEmployeesForParty(
                legalEntityId: $legalEntity->id,
                partyId: $party->id,
                status: Status::REORGANIZED
            )->get();

            $partyEmployees = $partyEmployees->merge($reorganizedEmployees)->unique('id')->values();
        }

        if ($partyEmployees->isEmpty()) {
            return;
        }

        $pivotEmployeeUsers = $this->getPivotEmployeeUsers($legalEntity->id);
        $partyUsers = $this->resolvePartyUsers($party, $legalEntity, $partyEmployees);

        if ($partyUsers->isEmpty()) {
            return;
        }

        // Stopped OWNERS must not pick OWNER back up from another employee that happens
        // to share the party (owner replacement). Binding is otherwise per-user only.
        $stoppedOwnerUserIds = Employee::where('legal_entity_id', $legalEntity->id)
            ->where('party_id', $party->id)
            ->where('employee_type', Role::OWNER->value)
            ->where('status', Status::STOPPED->value)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $employeeRequestsByEmail = EmployeeRequest::query()
            ->where('legal_entity_id', $legalEntity->id)
            ->whereIn('email', $partyUsers->pluck('email')->filter()->all())
            ->get()
            ->groupBy(fn (EmployeeRequest $request) => mb_strtolower((string) $request->email));

        $employeesCandidatesToSync = [];
        $usersToSync = $partyUsers->pluck('id')->all();

        $guards = collect(array_keys((array) config('auth.guards')))->values();
        $savedGuard = Auth::getDefaultDriver();
        $loginedRole = Session::get('first_login_role');

        setPermissionsTeamId($legalEntity->id);

        foreach ($partyUsers as $user) {
            $userEmployees = $this->resolveEmployeesForUser(
                $user,
                $partyEmployees,
                $pivotEmployeeUsers,
                $employeeRequestsByEmail->get(mb_strtolower((string) $user->email), collect()),
                $stoppedOwnerUserIds
            );

            $employeesCandidatesToSync = array_merge(
                $employeesCandidatesToSync,
                $userEmployees->map(fn (Employee $employee) => [
                    'employee_id' => $employee->id,
                    'user_id' => $user->id,
                ])->all()
            );

            $availRoles = $userEmployees
                ->map(fn (Employee $employee) => $employee->employeeType)
                ->unique()
                ->values()
                ->all();

            if (in_array(Role::OWNER->value, $availRoles, true) && $isLegalEntityCanBeReorganized) {
                $availRoles[] = Role::REORGANIZATION_OWNER->value;
            }

            // Preserve access when the logged-in role was chosen at first login and email was changed.
            if ($loginedRole && $user->id === Auth::id()) {
                $loginedEmployee = $userEmployees->firstWhere('employee_type', $loginedRole);

                if ($loginedEmployee) {
                    $employeesCandidatesToSync = array_merge(
                        $employeesCandidatesToSync,
                        [['employee_id' => $loginedEmployee->id, 'user_id' => $user->id]],
                        array_map(
                            fn ($userId) => ['employee_id' => $loginedEmployee->id, 'user_id' => $userId],
                            array_column(
                                array_filter(
                                    $pivotEmployeeUsers,
                                    fn ($puser) => $puser['employee_id'] === $loginedEmployee->id
                                ),
                                'user_id'
                            )
                        )
                    );

                    $availRoles = array_values(array_unique(array_merge($availRoles, [$loginedRole])));
                }

                if ($loginedRole === Role::OWNER->value && $isLegalEntityCanBeReorganized) {
                    $availRoles = array_values(array_unique(array_merge(
                        $availRoles,
                        [Role::REORGANIZATION_OWNER->value]
                    )));
                }
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');

            // Replace roles for this team: drop invented ones (e.g. SPECIALIST from unbound
            // party positions) so the next oauth/apps/authorize does not 422.
            foreach ($guards as $guard) {
                Auth::shouldUse($guard);
                $user->syncRoles($availRoles);
            }
        }

        [
            'employeesToDelete' => $employeesToDelete,
            'employeesToSync' => $employeesToSync,
        ] = $this->filterEmployeesSyncData($employeesCandidatesToSync, $pivotEmployeeUsers, $usersToSync);

        if (!empty($employeesToDelete)) {
            foreach ($employeesToDelete as $pair) {
                DB::table('employee_users')
                    ->where('employee_id', $pair['employee_id'])
                    ->where('user_id', $pair['user_id'])
                    ->delete();
            }
        }

        if (!empty($employeesToSync)) {
            DB::table('employee_users')->upsert($employeesToSync, ['employee_id', 'user_id']);
        }

        Auth::shouldUse($savedGuard);
    }

    /**
     * Users for this party in the LE: already linked via employee.user_id / pivot,
     * plus users whose email matches an employee request for these party employees.
     *
     * @param  Collection<int, Employee>  $partyEmployees
     * @return Collection<int, User>
     */
    protected function resolvePartyUsers(Party $party, LegalEntity $legalEntity, Collection $partyEmployees): Collection
    {
        $linkedUsers = User::allRelated($party->id, $legalEntity->id)->get();

        $requestEmails = EmployeeRequest::query()
            ->where('legal_entity_id', $legalEntity->id)
            ->where(function ($query) use ($partyEmployees) {
                $query->whereIn('employee_id', $partyEmployees->pluck('id')->all())
                    ->orWhere(function ($fallback) use ($partyEmployees) {
                        foreach ($partyEmployees as $employee) {
                            $fallback->orWhere(function ($match) use ($employee) {
                                $match->where('employee_type', $employee->employeeType)
                                    ->where('position', $employee->position);

                                $startDate = $employee->getRawOriginal('start_date');
                                if ($startDate === null) {
                                    $match->whereNull('start_date');
                                } else {
                                    $match->where('start_date', $startDate);
                                }
                            });
                        }
                    });
            })
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn (string $email) => mb_strtolower($email))
            ->unique()
            ->values()
            ->all();

        $emailUsers = empty($requestEmails)
            ? collect()
            : User::query()
                ->where('party_id', $party->id)
                ->whereIn(DB::raw('LOWER(email)'), $requestEmails)
                ->get();

        return $linkedUsers->merge($emailUsers)->unique('id')->values();
    }

    /**
     * Resolve employees that belong to a specific user (not the whole party).
     *
     * @param  Collection<int, Employee>  $partyEmployees
     * @param  array<int, array{employee_id: int, user_id: int}>  $pivotEmployeeUsers
     * @param  Collection<int, EmployeeRequest>  $userRequests
     * @param  Collection<int, int>  $stoppedOwnerUserIds
     * @return Collection<int, Employee>
     */
    protected function resolveEmployeesForUser(
        User $user,
        Collection $partyEmployees,
        array $pivotEmployeeUsers,
        Collection $userRequests,
        Collection $stoppedOwnerUserIds
    ): Collection {
        $pivotEmployeeIds = collect($pivotEmployeeUsers)
            ->filter(fn (array $item) => $item['user_id'] === $user->id)
            ->pluck('employee_id')
            ->all();

        $requestMatchedIds = $partyEmployees
            ->filter(function (Employee $employee) use ($userRequests) {
                return $userRequests->contains(function (EmployeeRequest $request) use ($employee) {
                    if ($request->employeeId !== null && (int) $request->employeeId === (int) $employee->id) {
                        return true;
                    }

                    if ($request->employeeType !== $employee->employeeType
                        || $request->position !== $employee->position
                    ) {
                        return false;
                    }

                    $requestStart = $request->getRawOriginal('start_date');
                    $employeeStart = $employee->getRawOriginal('start_date');

                    return $requestStart === $employeeStart;
                });
            })
            ->pluck('id')
            ->all();

        return $partyEmployees
            ->filter(function (Employee $employee) use ($user, $pivotEmployeeIds, $requestMatchedIds, $stoppedOwnerUserIds) {
                $belongsToUser = (int) $employee->userId === (int) $user->id
                    || in_array($employee->id, $pivotEmployeeIds, true)
                    || in_array($employee->id, $requestMatchedIds, true);

                if (!$belongsToUser) {
                    return false;
                }

                // Do not let a replaced (stopped) OWNER inherit OWNER from another binding path.
                if ($employee->employeeType === Role::OWNER->value
                    && $stoppedOwnerUserIds->contains($user->id)
                    && (int) $employee->userId !== (int) $user->id
                ) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Get all existing employee-user relations from the pivot table for a given legal entity.
     *
     * @return array<int, array{employee_id: int, user_id: int}>
     */
    protected function getPivotEmployeeUsers(int $legalEntityId): array
    {
        $pivotEmployeeUsers = Employee::getEmployeesViaPivot($legalEntityId)->get()->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'users' => $employee->users()->allRelatedIds()->all(),
        ])->toArray();

        return collect($pivotEmployeeUsers)->flatMap(fn ($item) => collect($item['users'])->map(fn ($userId) => [
            'employee_id' => $item['id'],
            'user_id' => $userId,
        ]))->values()->all();
    }

    /**
     * @param  array<int, array{employee_id: int, user_id: int}>  $employeesCandidatesToSync
     * @param  array<int, array{employee_id: int, user_id: int}>  $pivotEmployeeUsers
     * @param  array<int, int>  $usersToSync
     * @return array{employeesToDelete: array<int, array{employee_id: int, user_id: int}>, employeesToSync: array<int, array{employee_id: int, user_id: int}>}
     */
    protected function filterEmployeesSyncData(array $employeesCandidatesToSync, array $pivotEmployeeUsers, array $usersToSync): array
    {
        $employeesToDelete = [];
        $employeesToSync = [];

        $employeesCandidatesToSync = collect($employeesCandidatesToSync)
            ->unique(fn ($item) => $item['employee_id'] . '_' . $item['user_id'])
            ->values()
            ->all();

        foreach ($usersToSync as $userId) {
            $pivotEmployee = collect($pivotEmployeeUsers)->filter(fn ($item) => $item['user_id'] === $userId)->pluck('employee_id')->all();
            $candidate = collect($employeesCandidatesToSync)->filter(fn ($item) => $item['user_id'] === $userId)->pluck('employee_id')->all();

            $employeesToRemove = array_diff($pivotEmployee, $candidate);
            $employeesToAdd = array_diff($candidate, $pivotEmployee);

            if (empty($employeesToRemove) && empty($employeesToAdd)) {
                continue;
            }

            foreach ($employeesToRemove as $employeeId) {
                $employeesToDelete[] = ['employee_id' => $employeeId, 'user_id' => $userId];
            }

            foreach ($employeesToAdd as $employeeId) {
                $employeesToSync[] = ['employee_id' => $employeeId, 'user_id' => $userId];
            }
        }

        return [
            'employeesToDelete' => $employeesToDelete,
            'employeesToSync' => $employeesToSync,
        ];
    }
}
