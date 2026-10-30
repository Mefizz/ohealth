<?php

declare(strict_types=1);

namespace App\Repositories;

use Log;
use Throwable;
use App\Core\Arr;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\Relations\Party;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\DB;
use App\Enums\Employee\RequestStatus;
use App\Models\Employee\EmployeeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

readonly class EmployeeRepository
{
    /** Scan local positions in bounded chunks; request history is not proof of Employee identity. */
    public function specialityClearCandidates(?int $legalEntityId = null, ?string $employeeUuid = null): LazyCollection
    {
        return Employee::whereNotNull('uuid')->whereHas('specialities')
            ->when($legalEntityId !== null, fn (Builder $query) => $query->whereLegalEntityId($legalEntityId))
            ->when($employeeUuid !== null, fn (Builder $query) => $query->whereUuid($employeeUuid))
            ->with('legalEntity')->lazyById(100);
    }

    public function refreshSpecialityClearPlan(int $employeeId, int $legalEntityId, string $employeeUuid): ?array
    {
        $employee = Employee::whereKey($employeeId)->whereLegalEntityId($legalEntityId)->whereUuid($employeeUuid)
            ->with(['party', 'specialities', 'legalEntity'])->first();

        return $employee ? $this->specialityClearPlan($employee) : null;
    }

    private function specialityClearPlan(Employee $employee): ?array
    {
        if (!$employee->party || !$employee->legalEntity
            || !Str::isUuid($employee->uuid) || !Str::isUuid($employee->party->uuid) || !Str::isUuid($employee->legalEntity->uuid)
            || $employee->specialities->isEmpty()) {
            return null;
        }

        if (EmployeeRequest::whereEmployeeId($employee->id)->whereLegalEntityId($employee->legalEntityId)
            ->whereIn('status', [RequestStatus::NEW->value, RequestStatus::SIGNED->value])->exists()) {
            return null;
        }

        $rows = $employee->specialities->sortBy('id');

        return [
            'employeeId' => $employee->id, 'legalEntityId' => $employee->legalEntityId,
            'employeeUuid' => $employee->uuid, 'legalEntityUuid' => $employee->legalEntity->uuid,
            'partyUuid' => $employee->party->uuid, 'employeeType' => $employee->employeeType,
            'employeeSnapshot' => $employee->getRawOriginal(),
            'specialitySnapshot' => $rows->map(fn ($row) => $row->getRawOriginal())->values()->all(),
            'suspectRows' => $rows->mapWithKeys(fn ($row) => [$row->id => $row->speciality])->all(),
        ];
    }

    /** Reconcile only this Employee's local codes against its current, explicitly identified eHealth record. */
    public function clearExtraSpecialities(array $plan, array $remote, bool $apply): array
    {
        $key = array_key_exists(strtolower($plan['employeeType']), $remote) ? strtolower($plan['employeeType']) : 'doctor';
        if ($key !== 'doctor' && array_key_exists('doctor', $remote) && data_get($remote, "{$key}.specialities") !== data_get($remote, 'doctor.specialities')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['specialities' => 'Ambiguous professional blocks in eHealth response.']);
        }
        $validated = Validator::make($remote, [
            'id' => ['required', 'uuid', Rule::in([$plan['employeeUuid']])],
            'legal_entity.id' => ['required', 'uuid', Rule::in([$plan['legalEntityUuid']])],
            'party.id' => ['required', 'uuid', Rule::in([$plan['partyUuid']])],
            'employee_type' => ['required', Rule::in([$plan['employeeType']])],
            'status' => ['required', Rule::in(['APPROVED', 'REORGANIZED', 'STOPPED', 'ENTERED IN ERROR', 'ENTERED_IN_ERROR', 'AUTODEACTIVATED'])],
            "{$key}.specialities" => ['present', 'array'],
            "{$key}.specialities.*.speciality" => ['required', 'string'],
        ])->validate();
        $remoteCodes = array_column(data_get($validated, "{$key}.specialities"), 'speciality');

        return DB::transaction(function () use ($plan, $remoteCodes, $apply): array {
            $employee = Employee::whereKey($plan['employeeId'])->whereLegalEntityId($plan['legalEntityId'])->lockForUpdate()->firstOrFail();
            $employee->load(['party', 'legalEntity', 'specialities' => fn ($query) => $query->lockForUpdate()]);
            if ($this->specialityClearPlan($employee) !== $plan) {
                return ['outcome' => 'changed_locally', 'rowIds' => [], 'codes' => []];
            }
            $extra = array_filter($plan['suspectRows'], fn ($code) => !in_array($code, $remoteCodes, true));
            if ($apply && $extra !== []) {
                $employee->specialities()->whereKey(array_keys($extra))->whereIn('speciality', array_values($extra))->delete();
            }

            return ['outcome' => $extra === [] ? 'matches_ehealth' : ($apply ? 'removed' : 'would_remove'), 'rowIds' => array_keys($extra), 'codes' => array_values(array_unique($extra))];
        });
    }

    public function requestMatchesEmployee(EmployeeRequest $request, Employee $employee): bool
    {
        if ((int) $request->legalEntityId !== (int) $employee->legalEntityId
            || ($request->employeeId !== null && (int) $request->employeeId !== (int) $employee->id)
            || ($request->partyId !== null && (int) $request->partyId !== (int) $employee->partyId)) {
            return false;
        }

        $employeeUuid = data_get($request->revision?->data, 'employee_uuid');
        if (filled($employeeUuid) && $employeeUuid !== $employee->uuid) {
            return false;
        }

        // Position and type are editable values, not identity, once the Employee is known.
        if ($request->employeeId !== null || filled($employeeUuid)) {
            return true;
        }

        $revisionEmployeeType = data_get($request->revision?->data, 'employee_request_data.employee_type');
        $revisionPosition = data_get($request->revision?->data, 'employee_request_data.position');
        if ((filled($request->employeeType) && $request->employeeType !== $employee->employeeType)
            || (filled($request->position) && $request->position !== $employee->position)
            || (filled($revisionEmployeeType) && $revisionEmployeeType !== $employee->employeeType)
            || (filled($revisionPosition) && $revisionPosition !== $employee->position)) {
            return false;
        }

        if ($request->partyId !== null) {
            return true;
        }

        $party = $employee->party;
        $partyUuid = data_get($request->revision?->data, 'party.uuid');
        $taxId = data_get($request->revision?->data, 'party.tax_id');

        return $party && ((filled($partyUuid) && $partyUuid === $party->uuid)
            || (filled($taxId) && $taxId === $party->taxId));
    }

    public function employeeForRequest(EmployeeRequest $request): ?Employee
    {
        $employee = $request->employee;

        return $employee && $this->requestMatchesEmployee($request, $employee) ? $employee : null;
    }

    public function latestDraftForEmployee(Employee $employee): ?EmployeeRequest
    {
        return EmployeeRequest::whereEmployeeId($employee->id)
            ->whereLegalEntityId($employee->legalEntityId)
            ->whereNull('uuid')
            ->whereNull('applied_at')
            ->with('revision')
            ->latest()
            ->latest('id')
            ->get()
            ->first(fn (EmployeeRequest $request): bool => $this->requestMatchesEmployee($request, $employee));
    }

    public function requestForEmployeeDetails(Employee $employee, ?string $divisionUuid, ?string $startDate): ?EmployeeRequest
    {
        $requests = EmployeeRequest::whereLegalEntityId($employee->legalEntityId)
            ->whereEmployeeType($employee->employeeType)
            ->wherePosition($employee->position)
            ->whereNotNull('uuid')
            ->where(fn (Builder $query) => $query->whereEmployeeId($employee->id)->orWhereNull('employee_id'))
            ->where(fn (Builder $query) => $query->wherePartyId($employee->partyId)->orWhereNull('party_id'))
            ->where('division_uuid', $divisionUuid)
            ->where('start_date', $startDate)
            ->with('revision')
            ->latest('applied_at')
            ->latest('id')
            ->get()
            ->filter(fn (EmployeeRequest $request): bool => $this->requestMatchesEmployee($request, $employee));

        $linked = $requests->first(fn (EmployeeRequest $request): bool => (int) $request->employeeId === (int) $employee->id);

        return $linked ?? ($requests->count() === 1 ? $requests->first() : null);
    }

    /** @return list<array<string, mixed>> */
    public function originalSpecialities(Employee|EmployeeRequest|null $source, ?int $legalEntityId, string $employeeType): array
    {
        $employee = $source instanceof EmployeeRequest ? $this->employeeForRequest($source) : $source;

        if (!$employee
            || !$employee->exists
            || (int) $employee->legalEntityId !== $legalEntityId
            || $employee->employeeType !== $employeeType
            || $employee->status !== \App\Enums\Status::APPROVED) {
            return [];
        }

        $employee->loadMissing('specialities');

        return Arr::toCamelCase($employee->specialities->toArray());
    }

    public function activeForLegalEntity(User $user, LegalEntity $legalEntity, array $employeeTypes): ?Employee
    {
        return $user->employees()->whereLegalEntityId($legalEntity->id)->active()->whereIn('employee_type', $employeeTypes)->first();
    }

    /**
     * Creates a new EmployeeRequest draft from prepared data.
     * This is a universal method that only handles database persistence.
     *
     * @param  array  $employeeRequestData  The prepared data for the request itself.
     * @param  LegalEntity  $legalEntity  The associated LegalEntity model.
     * @param  Employee|null  $employee  (Optional) The existing employee being edited.
     * @return EmployeeRequest
     */
    public function createEmployeeRequestDraft(array $employeeRequestData, LegalEntity $legalEntity, ?Employee $employee = null): EmployeeRequest
    {
        $employeeRequest = new EmployeeRequest();
        $employeeRequest->fill($employeeRequestData);
        $employeeRequest->status = RequestStatus::NEW;
        $employeeRequest->legalEntity()->associate($legalEntity);

        if ($employee) {
            $employeeRequest->employee()->associate($employee);
        }

        $employeeRequest->save();

        return $employeeRequest;
    }

    /**
     * @param  Employee|EmployeeRequest  $employee  the model or identifier (ID or UUID) of the employee to update
     * @param  array  $party
     * @param  array  $documents
     * @param  array  $phones
     * @param  array|null  $educations
     * @param  array|null  $specialities
     * @param  array|null  $qualifications
     * @param  array|null  $scienceDegree
     * @return Employee|EmployeeRequest Updated employee
     * @throws Throwable
     */
    public function updateDetails(
        Employee|EmployeeRequest $employee,
        array $party,
        array $documents,
        array $phones,
        ?array $educations = null,
        ?array $specialities = null,
        ?array $qualifications = null,
        ?array $scienceDegree = null,
    ): Employee|EmployeeRequest {
        $model = $employee;

        DB::transaction(function () use ($model, $party, $documents, $phones, $educations, $specialities, $qualifications, $scienceDegree) {
            $partyAttributes = array_diff_key($party, array_flip(['documents', 'phones']));

            $this->updatePartyByUuid($model, $partyAttributes);

            $model->party->syncMany('documents', $documents);
            $model->party->syncMany('phones', $phones);
            $model->syncMany('educations', $educations);
            $model->syncMany('specialities', $specialities);
            $model->syncMany('qualifications', $qualifications);

            if (!empty($scienceDegree)) {
                $model->scienceDegree()->updateOrCreate([], $scienceDegree);
            } else {
                $model->scienceDegree()->delete();
            }
        });

        return $model;
    }

    /**
     * Returns a Query Builder for Parties, sorted by the latest activity date.
     *
     * Mechanism:
     * Builds a query for parties linked to employees of a legal entity, ordered by latest employee activity.
     *
     * @param  int  $legalEntityId
     * @return Builder|QueryBuilder
     */
    public function getPartiesWithLatestActivityQuery(int $legalEntityId): Builder|QueryBuilder
    {
        $employeesQuery = Employee::selectRaw('party_id, MAX(updated_at) as last_employee_at')
            ->where('legal_entity_id', $legalEntityId)
            ->groupBy('party_id');

        return Party::query()
            ->select('parties.*')
            ->addSelect([
                'emp_stat.last_employee_at',
            ])
            ->leftJoinSub($employeesQuery, 'emp_stat', 'parties.id', '=', 'emp_stat.party_id')
            ->with([
                'phones',
                'employees' => fn ($q) => $q
                    ->where('legal_entity_id', $legalEntityId)
                    ->orderByDesc('updated_at')
                    ->with(['division']),
            ])
            ->orderByRaw("COALESCE(emp_stat.last_employee_at, '1970-01-01') DESC");
    }

    /**
     * The logic behind the party update or create is as follows:
     * 1. Check party by UUID. Possible scenario: the party already exists in the system
     * 2. If user already has a party, update it.
     * 3. If user does not have a party, but there is a party with the same UUID, update it and establish the relation.
     * 4. If neither of the above, create a new party and establish the relation.
     * 5. If the model is linked to a different Party than the one that already owns this UUID,
     *    relink to that Party and update it. Never copy the UUID onto the currently linked row.
     */
    protected function updatePartyByUuid(Employee|EmployeeRequest $model, array $party): void
    {
        unset($party['email']);
        $partyUuid = Arr::get($party, 'uuid');
        // A missing uuid must not become WHERE uuid IS NULL: that row is a different person
        // created moments earlier, and the next hire would overwrite their name.
        $partyByUuid = is_string($partyUuid) && $partyUuid !== ''
            ? Party::whereUuid($partyUuid)->first()
            : null;

        // If the model doesn't have a party and party doesn't exist, create new one. It's a brand-new person
        if (!$partyByUuid && !$model->party) {
            $newParty = new Party($party);
            $newParty->save();
            $model->party()->associate($newParty)->save();

            // If the model doesn't have a related party but the party already exists, update it and relate - the scenario of a new employee with already created person/party
        } elseif ($partyByUuid && !$model->party) {
            $partyByUuid->update($party);
            $model->party()->associate($partyByUuid)->save();

            // The model already has a related party, update it and change the UUID - the case when eHealth creates another party, probably merge scenario
        } elseif (!$partyByUuid && $model->party) {
            $model->party()->update($party);

            // Both the model and the party exist, check if they are the same
        } elseif ($partyByUuid && $model->party) {

            // If both the ID and UUID are the same, just update
            if ($partyByUuid->id === $model->party->id && $partyByUuid->uuid === $model->party->uuid) {
                $model->party()->update($party);
            } else {
                // party()->update() is a mass update constrained to the current party_id
                // at the moment party() is called. Writing this payload there copies the
                // eHealth UUID onto the draft row and hits parties_uuid_unique.
                // Relink first, then update the row that already owns the UUID.
                $previousPartyUuid = $model->party->uuid;
                $previousPartyId = $model->partyId;

                $model->party()->associate($partyByUuid)->save();
                $partyByUuid->update($party);

                if ($model instanceof Employee) {
                    EmployeeRequest::whereEmployeeId($model->id)
                        ->whereLegalEntityId($model->legalEntityId)
                        ->wherePartyId($previousPartyId)
                        ->update(['party_id' => $partyByUuid->id]);
                }

                Log::warning('Potential party merge scenario detected', [
                    'model_party_uuid' => $previousPartyUuid,
                    'ehealth_party_uuid' => $partyByUuid->uuid,
                    'updated_with_ehealth_data' => true,
                    'relinked_to_existing_party' => true,
                ]);
            }
        }
    }
}
