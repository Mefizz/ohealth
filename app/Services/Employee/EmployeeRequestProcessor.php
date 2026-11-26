<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Api\Responses\Collections\EmployeeRequestGetMany;
use App\Dto\EmployeeRequest\Model as EmployeeRequestData;
use App\Models\User;
use RuntimeException;
use Throwable;
use App\Core\Arr;
use App\Enums\Employee\RequestStatus as LocalStatus;
use App\Enums\Employee\RevisionStatus;
use App\Enums\JobStatus;
use App\Enums\Status;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use App\Repositories\Repository;
use App\Services\Employee\EmployeeRequestMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeRequestProcessor
{
    public const string OUTCOME_APPROVED = 'approved';
    public const string OUTCOME_REJECTED = 'rejected';
    public const string OUTCOME_EXPIRED = 'expired';
    public const string OUTCOME_PENDING = 'pending';
    public const string OUTCOME_FAILED = 'failed';

    public function __construct(protected EmployeeRequestMatcher $matcher)
    {
    }

    /**
     * Sync one pending local employee request against eHealth (same outcome as login EmployeeCreate).
     *
     * @return array{outcome: string, message: string}
     */
    public function syncSinglePendingRequest(EmployeeRequest $request, LegalEntity $legalEntity, ?string $token = null): array
    {
        $request->loadMissing(['revision', 'employee', 'party', 'division']);
        if (!$request->needsEhealthSync()) {
            return ['outcome' => self::OUTCOME_FAILED, 'message' => __('employees.sync.employee_request_not_pending')];
        }
        $token ??= session()->get(config('ehealth.api.oauth.bearer_token'));
        if (!$token) {
            return ['outcome' => self::OUTCOME_FAILED, 'message' => __('employees.sync.session_token_missing')];
        }
        $response = EHealth::employeeRequest()->withToken($token)->getDetails($request->uuid);
        $outcome = $this->syncRequestDetails($request, $legalEntity, $response, $token);
        $message = match ($outcome) {
            self::OUTCOME_APPROVED => __('employees.sync.employee_request_success'),
            self::OUTCOME_PENDING => __('employees.sync.employee_request_still_pending'),
            default => __('employees.sync.employee_request_status_updated', ['status' => strtoupper($outcome)]),
        };

        return ['outcome' => $outcome, 'message' => $message];
    }

    /** Shared detail import for a queued job and a single-request refresh. */
    public function syncRequestDetails(
        EmployeeRequest $request,
        LegalEntity $legalEntity,
        EHealthResponse $response,
        ?string $token = null,
    ): string {
        $details = $response->validate();
        $data = objectMapper()->map($details, EmployeeRequestData::class);
        $request->loadMissing('revision');
        $approved = $data->status === LocalStatus::APPROVED->value;
        $outdated = $approved && $request->revision?->status === RevisionStatus::OUTDATED;
        $revisionStatus = match ($data->status) {
            LocalStatus::REJECTED->value, LocalStatus::EXPIRED->value => RevisionStatus::OUTDATED,
            LocalStatus::APPROVED->value => $outdated ? RevisionStatus::OUTDATED : RevisionStatus::PENDING,
            default => RevisionStatus::SENT,
        };
        $request = Repository::employee()->saveRequestDetails(
            $data,
            $request,
            $legalEntity,
            $revisionStatus,
            $approved && !$outdated ? JobStatus::PARTIAL : JobStatus::COMPLETED,
            $response->json(),
        );
        // Importing an approved snapshot is not proof that its employee changes were applied locally.
        if ($approved && !in_array($request->revision?->status, [RevisionStatus::APPLIED, RevisionStatus::OUTDATED], true)) {
            $this->applyApprovedRequest($request, $details->all(), $token);
        }

        $request->refresh();

        return match ($request->status->value) {
            LocalStatus::APPROVED->value => self::OUTCOME_APPROVED,
            LocalStatus::REJECTED->value => self::OUTCOME_REJECTED,
            LocalStatus::EXPIRED->value => self::OUTCOME_EXPIRED,
            default => self::OUTCOME_PENDING,
        };
    }

    /**
     * Applies data from an APPROVED eHealth request to the local Employee entity.
     * Since the User Token response does not contain the created 'employee_id',
     * this method resolves the UUID by searching eHealth via Tax ID.
     *
     * @param  EmployeeRequest  $request  The local request entity.
     * @param  array  $eHealthData  Data array from the API response (primarily for status confirmation).
     * @throws Throwable
     */
    public function applyApprovedRequest(EmployeeRequest $request, array $eHealthData, ?string $token = null): void
    {
        Log::info('[EmployeeRequestProcessor] Start Apply.', [
            'request_uuid' => $request->uuid,
            'eHealth_status' => $eHealthData['status'] ?? 'N/A',
        ]);

        DB::transaction(function () use ($request, $eHealthData, $token) {
            $request = EmployeeRequest::query()->whereKey($request->id)
                ->whereLegalEntityId($request->legalEntityId)->lockForUpdate()->firstOrFail();
            $request->load(['revision', 'employee', 'party', 'division']);
            if (in_array($request->revision?->status, [RevisionStatus::APPLIED, RevisionStatus::OUTDATED], true)) {
                return;
            }

            // 1. Prepare Local Data (Source of Truth for content)
            $revisionData = $request->revision->data;
            $mappedLocalData = EHealth::employeeRequest()->mapCreate($revisionData);

            $taxId = $mappedLocalData['party']['tax_id'] ?? null;

            // An edit already identifies its employee; a create needs a unique validated list match.
            $employee = Repository::employee()->employeeForRequest($request);
            $employeeUuid = $employee?->uuid;
            if ($request->employeeId !== null && !$employee) {
                throw new RuntimeException('Cannot apply employee request to a different employee or position.');
            }
            if (!$employeeUuid && is_string($taxId) && $taxId !== '') {
                $remoteEmployee = $this->matcher->findApprovedForRequest($request, $taxId, $request->legalEntity->uuid, $token);
                $employeeUuid = $remoteEmployee['uuid'] ?? null;
            }
            if (!$employeeUuid) {
                throw new RuntimeException('Cannot resolve the approved employee from the request identity.');
            }
            $employee ??= Employee::whereUuid($employeeUuid)->first();

            if ($employee && !Repository::employee()->requestMatchesEmployee($request, $employee)) {
                throw new RuntimeException('Cannot apply employee request to a different employee or position.');
            }

            // Discovering an older approval must not restore a stopped position or replay an older revision.
            $newerApplied = $employee && Repository::employee()->hasNewerAppliedRequest($request, $employee);
            if ($employee && ($employee->status !== Status::APPROVED || $newerApplied)) {
                $request->update([
                    'employee_id' => $employee->id,
                    'party_id' => $employee->partyId,
                    'sync_status' => JobStatus::COMPLETED->value,
                ]);
                $request->revision->update(['status' => RevisionStatus::OUTDATED]);
                return;
            }

            $isNew = false;
            if (!$employee) {
                $isNew = true;
                Log::info("[EmployeeRequestProcessor] Creating NEW Employee locally with UUID {$employeeUuid}");

                $employee = new Employee();
                $employee->uuid = $employeeUuid;
            }

            // 4. Prepare System Overrides
            // We prioritize eHealth status and dates, but use local ID for Division
            $systemOverrides = Arr::only(
                $eHealthData,
                ['status', 'start_date', 'end_date', 'position', 'employee_type']
            );

            // 5. Merge Data: Revision (Base) + System Overrides
            $finalEmployeeData = array_merge(
                $mappedLocalData['employee'],
                $systemOverrides
            );

            // 6. Fill Model
            $employee->fill($finalEmployeeData);

            if ($isNew) {
                $employee->uuid = $employeeUuid; // Ensure UUID is set
                $employee->legalEntityId = $request->legalEntityId;
                $employee->userId = $request->userId;
                $employee->status = $systemOverrides['status'] ?? Status::APPROVED->value;

                if ($request->partyId) {
                    $employee->partyId = $request->partyId;
                }
            }

            // 7. Save to DB
            $employee->save();

            Log::info("[EmployeeRequestProcessor] Employee Saved. ID: {$employee->id}");

            // 9. Update Details (Party, Documents, Phones...)
            $partyData = $mappedLocalData['party'];
            if (!$isNew && $employee->partyId) {
                // A revision UUID may predate a confirmed canonical-party relink.
                unset($partyData['uuid']);
            }

            Repository::employee()->updateDetails(
                $employee,
                $partyData,
                $mappedLocalData['documents'],
                $mappedLocalData['phones'],
                $mappedLocalData['educations'] ?? null,
                $mappedLocalData['specialities'] ?? null,
                $mappedLocalData['qualifications'] ?? null,
                $mappedLocalData['science_degree'] ?? null,
                replaceScienceDegree: $mappedLocalData['has_science_degree']
            );

            $request->update(['employee_id' => $employee->id, 'party_id' => $employee->partyId]);

            // 10. Assign Roles to User
            $this->assignUserRoles($employee, $request->legalEntityId, $request->userId);

            // Keep the request creation time; only applied_at records the terminal decision.
            $appliedAt = $this->remoteAppliedAt($eHealthData);
            if (blank($employee->insertedAt)) {
                $employee->insertedAt = $appliedAt;
                $employee->save();
            }

            $request->update([
                'status' => LocalStatus::APPROVED,
                'applied_at' => $appliedAt,
                'sync_status' => JobStatus::COMPLETED->value,
            ]);

            if ($request->revision) {
                $request->revision->update(['status' => RevisionStatus::APPLIED]);
            }

            $this->markOlderPendingEditsSuperseded($request);
        });
    }

    /**
     * After a newer edit is applied, close older still-pending edits for the same employee
     * in this LE so login/sync cannot re-apply yesterday's phones/documents.
     */
    public function markOlderPendingEditsSuperseded(EmployeeRequest $applied): void
    {
        if (blank($applied->employeeId) || blank($applied->legalEntityId)) {
            return;
        }

        $olderPending = EmployeeRequest::query()
            ->with('revision')
            ->filterByLegalEntityId((int) $applied->legalEntityId)
            ->where('employee_id', $applied->employeeId)
            ->where('id', '!=', $applied->id)
            ->pendingEhealth()
            ->whereNotNull('uuid')
            ->where(function ($query) use ($applied): void {
                $query->where('created_at', '<', $applied->createdAt)
                    ->orWhere(function ($inner) use ($applied): void {
                        $inner->where('created_at', $applied->createdAt)
                            ->where('id', '<', $applied->id);
                    });
            })
            ->get();

        foreach ($olderPending as $oldRequest) {
            $oldRequest->update([
                'status' => LocalStatus::EXPIRED,
                'applied_at' => now(),
            ]);
            $oldRequest->revision?->update(['status' => RevisionStatus::OUTDATED]);
        }
    }

    /**
     * eHealth request updated_at is the authoritative applied_at for terminal statuses.
     */
    private function remoteAppliedAt(array $remoteData): Carbon
    {
        return filled($remoteData['updated_at'] ?? null)
            ? Carbon::parse($remoteData['updated_at'])
            : now();
    }

    /**
     * Among approved local requests, keep only the newest per employee_id.
     * Creates (no employee_id) are kept individually. Older superseded edits are returned separately.
     *
     * @param  Collection<int, EmployeeRequest>  $approvedRequests
     * @return array{apply: Collection<int, EmployeeRequest>, superseded: Collection<int, EmployeeRequest>}
     */
    public function partitionLatestApprovedPerEmployee(Collection $approvedRequests): array
    {
        $sorted = $approvedRequests
            ->sortBy(fn (EmployeeRequest $request): array => [
                $request->createdAt?->timestamp ?? 0,
                $request->id,
            ])
            ->values();

        $apply = collect();
        $superseded = collect();

        foreach ($sorted->groupBy(
            fn (EmployeeRequest $request): string => filled($request->employeeId)
                ? 'employee-'.$request->employeeId
                : 'create-'.$request->id
        ) as $group) {
            /** @var Collection<int, EmployeeRequest> $group */
            $latest = $group->last();
            $apply->push($latest);
            $superseded = $superseded->merge($group->filter(
                fn (EmployeeRequest $request): bool => $request->id !== $latest->id
            ));
        }

        return [
            'apply' => $apply->values(),
            'superseded' => $superseded->values(),
        ];
    }

    /**
     * Processes a batch of remote Employee Request data from eHealth.
     */
    public function processBatch(EmployeeRequestGetMany $eHealthData, LegalEntity $legalEntity): void
    {
        // List summaries only discover work. Details are imported and applied after all status pages.
        Repository::employee()->syncRequestSummaries($eHealthData, $legalEntity);
    }

    /**
     * Assigns roles to the user associated with the employee.
     */
    private function assignUserRoles(Employee $employee, int $legalEntityId, ?int $requestUserId = null): void
    {
        // Link User to Party if missing (critical for the User->Employee relation)
        if ($requestUserId && $employee->partyId) {
            $user = User::find($requestUserId);
            if ($user && !$user->partyId) {
                $user->partyId = $employee->partyId;
                $user->save();
            }
        }

        $users = $employee->party->users()->get();

        if ($users->isEmpty() && $requestUserId) {
            $requestUser = User::find($requestUserId);

            if ($requestUser) {
                $users = collect([$requestUser]);
            }
        }

        if ($users->isEmpty()) {
            return;
        }

        $roleName = $employee->employeeType;
        setPermissionsTeamId($legalEntityId);

        foreach ($users as $user) {
            $employee->users()->syncWithoutDetaching([$user->id]);

            if (!$user->partyId && $employee->partyId) {
                $user->partyId = $employee->partyId;
                $user->save();
            }

            // Assign Role based on Employee Type
            if ($roleName && !$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
    }
}
