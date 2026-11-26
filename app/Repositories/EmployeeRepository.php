<?php

declare(strict_types=1);

namespace App\Repositories;

use Log;
use Throwable;
use UnexpectedValueException;
use App\Core\Arr;
use App\Enums\Status;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\Relations\Party;
use App\Models\Employee\Employee;
use App\Models\MedicalEvents\Sql\Approval;
use App\Exceptions\MedicalEvents\ApprovalVerificationException;
use Illuminate\Support\Facades\DB;
use App\Enums\Employee\RequestStatus;
use App\Enums\Employee\RevisionStatus;
use App\Enums\JobStatus;
use App\Dto\EmployeeRequest\Model as EmployeeRequestData;
use App\Classes\eHealth\Api\Responses\Collections\EmployeeRequestGetMany;
use Carbon\Carbon;
use App\Models\Employee\EmployeeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

readonly class EmployeeRepository
{
    public function syncRequestSummaries(EmployeeRequestGetMany $summaries, LegalEntity $legalEntity): void
    {
        DB::transaction(function () use ($summaries, $legalEntity): void {
            // Serialize discovery for the facility so concurrent status pages cannot insert duplicate UUIDs.
            LegalEntity::whereKey($legalEntity->id)->lockForUpdate()->firstOrFail();
            foreach ($summaries as $summary) {
                if ($summary['edrpou'] !== $legalEntity->edrpou) {
                    throw new UnexpectedValueException('Employee request list belongs to another legal entity.');
                }
                $request = EmployeeRequest::query()->whereLegalEntityId($legalEntity->id)
                    ->whereUuid($summary['uuid'])->with('revision')->first();
                if (!$request) {
                    EmployeeRequest::create([
                        'uuid' => $summary['uuid'],
                        'status' => $summary['status'],
                        'legal_entity_id' => $legalEntity->id,
                        'inserted_at' => Carbon::parse($summary['inserted_at']),
                        'created_at' => Carbon::parse($summary['inserted_at']),
                        'sync_status' => JobStatus::PARTIAL->value,
                    ]);
                    continue;
                }

                if ($request->isPendingEhealth() || !$request->revision
                    || $request->status->value !== $summary['status']
                    || ($request->status === RequestStatus::APPROVED && $request->employeeId === null
                        && $request->revision->status !== RevisionStatus::OUTDATED)) {
                    $request->update(['sync_status' => JobStatus::PARTIAL->value]);
                }
            }
        });
    }

    /** Save the request and its revision together; failed application remains PARTIAL for retry. */
    public function saveRequestDetails(
        EmployeeRequestData $data,
        EmployeeRequest $request,
        LegalEntity $legalEntity,
        RevisionStatus $revisionStatus,
        JobStatus $syncStatus,
        array $response,
    ): EmployeeRequest {
        return DB::transaction(function () use ($data, $request, $legalEntity, $revisionStatus, $syncStatus, $response): EmployeeRequest {
            $request = EmployeeRequest::query()
                ->whereKey($request->id)
                ->whereLegalEntityId($legalEntity->id)
                ->whereUuid($data->uuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($data->legalEntityUuid !== null && $data->legalEntityUuid !== $legalEntity->uuid) {
                throw new UnexpectedValueException('Employee request belongs to another legal entity.');
            }

            // Preserve a terminal decision when an older pending response arrives concurrently.
            if ($data->status === RequestStatus::NEW->value && !$request->isPendingEhealth() && $request->revision !== null) {
                return $request->load(['revision', 'employee', 'party', 'division']);
            }
            $alreadyApplied = $data->status === RequestStatus::APPROVED->value
                && $request->revision?->status === RevisionStatus::APPLIED
                && $request->employeeId !== null;
            if ($alreadyApplied) {
                $revisionStatus = RevisionStatus::APPLIED;
                $syncStatus = JobStatus::COMPLETED;
            } elseif ($data->status === RequestStatus::APPROVED->value && $request->revision?->status === RevisionStatus::OUTDATED) {
                $revisionStatus = RevisionStatus::OUTDATED;
                $syncStatus = JobStatus::COMPLETED;
            }

            $data->toModel($request);
            if (in_array('division_uuid', $data->providedFields, true)) {
                $request->divisionId = $data->divisionUuid === null
                    ? null
                    : $legalEntity->divisions()->whereUuid($data->divisionUuid)->value('id');
            }
            if ($request->userId === null && filled($request->email)) {
                $request->userId = User::whereEmail($request->email)->value('id');
            }
            $request->syncStatus = $syncStatus->value;
            if (!$alreadyApplied) {
                $request->appliedAt = in_array($data->status, [RequestStatus::REJECTED->value, RequestStatus::EXPIRED->value], true)
                    ? ($data->updatedAt === null ? now() : Carbon::parse($data->updatedAt))
                    : null;
            }
            $request->saveOrFail();

            Repository::revision()->saveRevision($request, [
                'data' => $data->toRevisionData($request->revision?->data ?? [], $request->divisionId),
                'ehealth_response' => $response,
                'status' => $revisionStatus,
            ]);

            return $request->load(['revision', 'employee', 'party', 'division']);
        });
    }

    public function hasPartyInLegalEntity(int $partyId, int $legalEntityId): bool
    {
        return Employee::wherePartyId($partyId)->whereLegalEntityId($legalEntityId)->exists();
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

    public function hasNewerAppliedRequest(EmployeeRequest $request, Employee $employee): bool
    {
        return $request->insertedAt !== null && EmployeeRequest::query()
            ->whereEmployeeId($employee->id)
            ->whereLegalEntityId($request->legalEntityId)
            ->where('inserted_at', '>', $request->insertedAt)
            ->whereHas('revision', fn (Builder $revision) => $revision->where('status', RevisionStatus::APPLIED))
            ->exists();
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
            || $employee->status !== Status::APPROVED) {
            return [];
        }

        $employee->loadMissing('specialities');

        return Arr::toCamelCase($employee->specialities->toArray());
    }

    public function activeForLegalEntity(User $user, LegalEntity $legalEntity, array $employeeTypes, ?string $employeeUuid = null): ?Employee
    {
        return Employee::forUserInLegalEntity($user, $legalEntity)->whereIn('employee_type', $employeeTypes)
            ->when($employeeUuid !== null, fn (Builder $query) => $query->whereUuid($employeeUuid))
            ->orderBy('id')->first();
    }

    /**
     * Select an active employee linked to the user in the given legal entity for approval creation.
     *
     * Requires an eHealth UUID and an available login role granting `approval:create`,
     * as checked by the `createForEmployee` policy. Prefers DOCTOR, then SPECIALIST,
     * then other eligible types; employees of the same priority are ordered by ID.
     *
     * @param  User  $user  The user whose linked employees are searched.
     * @param  LegalEntity  $legalEntity  The legal entity in which the approval is created.
     * @return Employee The first eligible employee.
     * @throws ApprovalVerificationException If no eligible employee is found.
     */
    public function forApproval(User $user, LegalEntity $legalEntity): Employee
    {
        $employee = $user->employees()
            ->whereLegalEntityId($legalEntity->id)
            ->active()
            ->whereNotNull('uuid')
            ->orderByRaw("CASE employee_type WHEN 'DOCTOR' THEN 0 WHEN 'SPECIALIST' THEN 1 ELSE 2 END")
            ->orderBy('employees.id')
            ->get()
            ->first(static fn (Employee $employee): bool => $user->can('createForEmployee', [Approval::class, $employee]));

        if ($employee === null) {
            throw new ApprovalVerificationException(ApprovalVerificationException::EMPLOYEE_UNAVAILABLE);
        }

        return $employee;
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
     * @param  array|null  $documents
     * @param  array|null  $phones
     * @param  array|null  $educations
     * @param  array|null  $specialities
     * @param  array|null  $qualifications
     * @param  array|null  $scienceDegree
     * @param  array{uuid: string, status: string, end_date?: string|null}|null  $employment  Selected position from Get Employee details.
     * @param  bool  $replaceScienceDegree  False when the source omitted the optional science_degree section.
     * @return Employee|EmployeeRequest Updated employee
     * @throws Throwable
     */
    public function updateDetails(
        Employee|EmployeeRequest $employee,
        array $party,
        ?array $documents,
        ?array $phones,
        ?array $educations = null,
        ?array $specialities = null,
        ?array $qualifications = null,
        ?array $scienceDegree = null,
        ?array $employment = null,
        bool $replaceScienceDegree = true,
    ): Employee|EmployeeRequest {
        $model = $employee;

        DB::transaction(function () use ($model, $party, $documents, $phones, $educations, $specialities, $qualifications, $scienceDegree, $employment, $replaceScienceDegree) {
            if ($model instanceof Employee && $employment !== null) {
                $status = Status::tryFrom(str_replace(' ', '_', $employment['status']));
                if (!$status?->isEmploymentStatus()) {
                    throw new UnexpectedValueException('Невідомий статус посади у відповіді ЕСОЗ.');
                }

                // Reject another position's details or a response overtaken by a concurrent employment update.
                $model->newQuery()
                    ->whereKey($model->id)
                    ->whereUuid(strtolower($employment['uuid']))
                    ->where('status', $model->getRawOriginal('status'))
                    ->where('end_date', $model->getRawOriginal('end_date'))
                    ->lockForUpdate()
                    ->firstOrFail();

                $model->update(['status' => $status] + Arr::only($employment, ['end_date']));
            }

            $partyAttributes = array_diff_key($party, array_flip(['documents', 'phones']));

            $this->updatePartyByUuid($model, $partyAttributes);

            $model->party->syncMany('documents', $documents);
            $model->party->syncMany('phones', $phones);
            $model->syncMany('educations', $educations);
            $model->syncMany('specialities', $specialities);
            $model->syncMany('qualifications', $qualifications);

            if ($replaceScienceDegree) {
                if (!empty($scienceDegree)) {
                    $model->scienceDegree()->updateOrCreate([], $scienceDegree);
                } else {
                    $model->scienceDegree()->delete();
                }
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
