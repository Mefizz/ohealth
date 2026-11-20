<?php

declare(strict_types=1);

namespace App\Models\MedicalEvents\Sql;

use App\Casts\UtcDateTimeCast;
use App\Contracts\SubmittedToEHealth;
use App\Enums\JobStatus;
use App\Models\CarePlan;
use App\Models\Declaration;
use App\Models\EhealthLink;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\Approval;
use App\Models\MedicalEvents\Sql\CodeableConcept;
use App\Models\MedicalEvents\Sql\Coding;
use App\Models\MedicalEvents\Sql\DiagnosticReport;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\MedicalEvents\Sql\Period;
use App\Models\MedicalEvents\Sql\Procedure;
use App\Models\MedicalEvents\Sql\Quantity;
use App\Models\Person\Person;
use App\Models\Preperson;
use App\Models\User;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class ServiceRequestRequest extends Model implements SubmittedToEHealth
{
    use HasCamelCasing;

    protected $table = 'service_request_requests';

    protected $fillable = [
        'uuid',
        'employee_id',
        'person_id',
        'division_id',
        'status',
        'request_number',
        'started_at',
        'ended_at',
        'service_id',
        'quantity',
        'program_id',
        'intent_id',
        'category_id',
        'based_on_id',
        'context_id',
        'priority_id',
        'note',
        'patient_instruction',
        'reason_reference',
        'inform_with',
        'supporting_info',
        'performer_id',
        'location_reference_id',
        'performer_type_id',

        'resource_metadata',
        'used_by_employee_id',
        'used_by_legal_entity_id',
        'used_by_division_id',
        'completed_with_id',
        'program_service_id',
        'details_synced_at',
        'clinical_status',
        'program_processing_status',
        'authored_on',
        'explanatory_letter',
        'status_reason_id',
        'code_identifier_id',
        'program_identifier_id',
        'requester_employee_id',
        'requester_legal_entity_id',
        'subject_identifier_id',
        'quantity_id',
        'remaining_quantity_id',
        'occurrence_date_time',
    ];

    protected $casts = [
        'started_at' => UtcDateTimeCast::class,
        'ended_at' => UtcDateTimeCast::class,
        'quantity' => 'decimal:10',

        'resource_metadata' => 'array',
        'details_synced_at' => 'datetime',
        'supporting_info' => 'array',
        'reason_reference' => 'array',
    ];

    #[Scope]
    protected function forPatientWithReferralContext(Builder $query, int $personId): Builder
    {
        return $query->wherePersonId($personId)
            ->with(['person', 'employee', 'requesterLegalEntity', 'priority']);
    }

    /** Record selection only; callers retain the search/view Policy gates. */
    #[Scope]
    protected function readableBy(Builder $query, User $user, Person $patient, LegalEntity $legalEntity): Builder
    {
        $query->wherePersonId($patient->id);
        $hasPatientAccess = Declaration::accessGrantedTo($user, $patient, $legalEntity)->exists()
            || Approval::accessGrantedTo($user, $patient, $legalEntity)->exists();
        if ($hasPatientAccess) {
            return $query;
        }

        return $query->where(static fn (Builder $records): Builder => $records
            ->whereHas('employee', static fn (Builder $employee): Builder => $employee->whereLegalEntityId($legalEntity->id))
            ->orWhereHas('requesterLegalEntity', static fn (Builder $identifier): Builder => $identifier->whereValue($legalEntity->uuid))
            ->orWhere(static fn (Builder $carePlan): Builder => $carePlan->basedOnApprovedCarePlan($patient, $user, $legalEntity))
            ->orWhere(static fn (Builder $carePlan): Builder => $carePlan->basedOnCarePlanOfLegalEntity($patient, $legalEntity)));
    }

    #[Scope]
    protected function forRegistry(Builder $query): Builder
    {
        return $query->with(['basedOn', 'context', 'category.coding', 'priority', 'programReference'])
            ->withExists(['ehealthLinks as submission_pending' => static fn (Builder $link): Builder => $link
                ->whereStatus(JobStatus::PENDING->value)]);
    }

    /** Date values are validated by the owning component before selection. */
    #[Scope]
    protected function filtered(Builder $query, array $filters): Builder
    {
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }
        foreach (['started_at', 'ended_at'] as $column) {
            foreach (['from' => '>=', 'to' => '<='] as $bound => $operator) {
                if (!empty($filters[$column.'_'.$bound])) {
                    $query->whereDate($column, $operator, $filters[$column.'_'.$bound]);
                }
            }
        }

        return $query;
    }

    /**
     * Find issued, incoming and EMR-linked referrals without hydrating every EMR.
     * FHIR context/basedOn contain Identifier keys, never the local encounter primary key.
     * The caller authorizes the patient/encounter; EMR Policy permissions and readable scopes restrict subqueries.
     */
    #[Scope]
    protected function relatedToEncounter(Builder $query, Encounter $encounter, Person $patient, User $user, LegalEntity $legalEntity): Builder
    {
        $query->wherePersonId($patient->id);
        if (!Str::isUuid($encounter->uuid)) {
            // An unsynced encounter can reference an incoming referral but has no remote context of its own.
            $incoming = $encounter->incomingReferral?->value;

            return $query->whereIn('uuid', Str::isUuid($incoming) ? [$incoming] : []);
        }

        return $query->where(static function (Builder $referrals) use ($encounter, $patient, $user, $legalEntity): void {
            $referrals->whereHas('context', static fn (Builder $identifier): Builder => $identifier
                ->whereValue($encounter->uuid)
                ->where(static fn (Builder $type): Builder => $type->whereDoesntHave('type.coding')
                    ->orWhereHas('type.coding', static fn (Builder $coding): Builder => $coding->whereCode('encounter'))));

            if (Str::isUuid($encounter->incomingReferral?->value)) {
                $referrals->orWhere('uuid', $encounter->incomingReferral->value);
            }

            foreach ([Procedure::class, DiagnosticReport::class] as $recordType) {
                if ($user->cannot('viewAny', $recordType)) {
                    continue;
                }

                $related = $recordType::forPatient($patient)->forEncounter($encounter->uuid)
                    ->readableBy($user, $patient, $legalEntity)->select('based_on_id');
                // Identifier values can also be free text. Compare the UUID as text on every database.
                $referrals->orWhereExists(Identifier::selectRaw('1')
                    ->whereRaw('identifiers.value = CAST(service_request_requests.uuid AS CHAR(36))')
                    ->whereIn('identifiers.id', $related));
            }
        });
    }

    public function statusReasonConcept(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'status_reason_id');
    }

    public function codeReference(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'code_identifier_id');
    }
    public function programReference(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'program_identifier_id');
    }
    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'requester_employee_id');
    }
    public function requesterLegalEntity(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'requester_legal_entity_id');
    }
    public function subjectReference(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'subject_identifier_id');
    }
    public function usedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'used_by_employee_id');
    }

    public function usedByLegalEntity(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'used_by_legal_entity_id');
    }

    public function usedByDivision(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'used_by_division_id');
    }

    public function completedWith(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'completed_with_id');
    }

    public function programService(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'program_service_id');
    }

    public function quantityQuantity(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'quantity_id');
    }
    public function remainingQuantity(): BelongsTo
    {
        return $this->belongsTo(Quantity::class, 'remaining_quantity_id');
    }
    public function occurrencePeriod(): MorphOne
    {
        return $this->morphOne(Period::class, 'periodable');
    }

    private function referenceCollection(string $collection): BelongsToMany
    {
        return $this->belongsToMany(Identifier::class, 'service_request_references', 'service_request_id', 'identifier_id')->withPivotValue('collection', $collection)->withPivot('position')->orderByPivot('position');
    }
    public function basedOnReferences(): BelongsToMany
    {
        return $this->referenceCollection('basedOn');
    }
    public function supportingInfoReferences(): BelongsToMany
    {
        return $this->referenceCollection('supportingInfo');
    }
    public function reasonReferences(): BelongsToMany
    {
        return $this->referenceCollection('reasonReference');
    }
    public function permittedReferences(): BelongsToMany
    {
        return $this->referenceCollection('permittedResources');
    }
    public function specimenReferences(): BelongsToMany
    {
        return $this->referenceCollection('specimens');
    }
    public function focusReferences(): BelongsToMany
    {
        return $this->referenceCollection('focus');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'based_on_id');
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'context_id');
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Coding::class, 'intent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'category_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'performer_id');
    }

    public function locationReference(): BelongsTo
    {
        return $this->belongsTo(Identifier::class, 'location_reference_id');
    }

    public function performerType(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'performer_type_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(CodeableConcept::class, 'priority_id');
    }

    /**
     * Filter referrals issued under a care plan of the patient one of the user's employees has an active read approval
     * on.
     *
     * ABAC rule 14.
     *
     * @param  Builder  $query
     * @param  Person|Preperson  $patient
     * @param  User  $user
     * @param  LegalEntity  $legalEntity
     * @return Builder
     */
    #[Scope]
    protected function basedOnApprovedCarePlan(
        Builder $query,
        Person|Preperson $patient,
        User $user,
        LegalEntity $legalEntity
    ): Builder {
        if ($patient instanceof Preperson) {
            return $query->whereRaw('1 = 0');
        }

        $carePlanIds = CarePlan::wherePersonId($patient->id)
            ->whereIn('id', Approval::carePlanAccessGrantedTo($user, $legalEntity)->select('approvable_id'))
            ->selectRaw('CAST(care_plans.uuid AS CHAR(36))');

        return $query->where(static function (Builder $scope) use ($carePlanIds): void {
            $scope->whereHas('basedOnReferences', static fn (Builder $identifier): Builder => $identifier->whereIn('value', $carePlanIds)->whereHas('type.coding', static fn (Builder $coding): Builder => $coding->whereCode('care_plan')))
                ->orWhere(static fn (Builder $legacy): Builder => $legacy->whereNull('details_synced_at')->whereHas('basedOn', static fn (Builder $identifier): Builder => $identifier->whereIn('value', $carePlanIds)));
        });
    }

    /**
     * Filter referrals issued under a care plan of the patient managed by the current legal entity.
     *
     * ABAC rule 17.
     *
     * @param  Builder  $query
     * @param  Person|Preperson  $patient
     * @param  LegalEntity|null  $legalEntity
     * @return Builder
     */
    #[Scope]
    protected function basedOnCarePlanOfLegalEntity(
        Builder $query,
        Person|Preperson $patient,
        ?LegalEntity $legalEntity = null
    ): Builder {
        $legalEntity ??= legalEntity();
        if ($patient instanceof Preperson || $legalEntity === null) {
            return $query->whereRaw('1 = 0');
        }

        $carePlanIds = CarePlan::wherePersonId($patient->id)
            ->whereHas(
                'managingOrganization',
                static fn (Builder $identifier): Builder => $identifier->whereValue($legalEntity->uuid)
            )
            ->selectRaw('CAST(care_plans.uuid AS CHAR(36))');

        return $query->where(static function (Builder $scope) use ($carePlanIds): void {
            $scope->whereHas('basedOnReferences', static fn (Builder $identifier): Builder => $identifier->whereIn('value', $carePlanIds)->whereHas('type.coding', static fn (Builder $coding): Builder => $coding->whereCode('care_plan')))
                ->orWhere(static fn (Builder $legacy): Builder => $legacy->whereNull('details_synced_at')->whereHas('basedOn', static fn (Builder $identifier): Builder => $identifier->whereIn('value', $carePlanIds)));
        });
    }

    public function ehealthLinks(): MorphMany
    {
        return $this->morphMany(EhealthLink::class, 'linkable');
    }
}
