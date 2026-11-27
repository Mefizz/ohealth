<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Core\Arr;
use App\Dto\CodeableConcept\Model as CodeableConceptData;
use App\Dto\Identifier\Model as IdentifierData;
use App\Enums\CarePlanStatus;
use App\Enums\ResourceType;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Models\CarePlan;
use Illuminate\Support\Collection;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

/**
 * Map the validated draft form or full eHealth response to CarePlan attributes and relations.
 *
 * Persisted attributes need explicit target mappings because Eloquent exposes them
 * through attribute access instead of declared properties. The remaining values
 * are used to build the related concepts, identifiers and period.
 */
#[Map(source: CarePlanDetails::class)]
#[Map(source: CarePlanForm::class)]
#[Map(target: CarePlan::class)]
class Model
{
    #[Map(source: '[id]', if: new SourceClass(CarePlanDetails::class))]
    public string $uuid;

    #[Map(source: '[status]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public string $status = CarePlanStatus::DRAFT->value;

    #[Map(source: '[intent]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'intent', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public string $intent = 'order';

    #[Map(source: '[title]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'title', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public string $title = '';

    public string $category = '';

    public string $termsOfService = '';

    #[Map(source: '[context?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'context', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $context = null;

    #[Map(source: '[description?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'description', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $description = null;

    #[Map(source: '[note?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'note', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $note = null;

    #[Map(source: '[period][start]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'localPeriodStart', if: new SourceClass(CarePlanForm::class), transform: 'convertToEHealthISO8601')]
    public string $periodStart;

    #[Map(source: '[period][end?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'localPeriodEnd', if: new SourceClass(CarePlanForm::class), transform: 'convertToEHealthISO8601')]
    public ?string $periodEnd = null;

    public string $encounter = '';

    /** @var list<array<string, mixed>> */
    #[Map(source: '[addresses]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public array $addresses = [];

    #[Map(source: '[category]', if: new SourceClass(CarePlanDetails::class), transform: [CodeableConceptData::class, 'map'])]
    public CodeableConceptData $categoryConcept;

    #[Map(source: '[terms_of_service]', if: new SourceClass(CarePlanDetails::class), transform: [CodeableConceptData::class, 'map'])]
    public CodeableConceptData $termsOfServiceConcept;

    #[Map(source: '[status_reason?]', if: new SourceClass(CarePlanDetails::class), transform: [CodeableConceptData::class, 'map'])]
    public ?CodeableConceptData $statusReasonConcept = null;

    #[Map(source: '[requisition]', if: new SourceClass(CarePlanDetails::class))]
    public string $requisition;

    #[Map(source: '[author]', if: new SourceClass(CarePlanDetails::class))]
    public array $author;

    #[Map(source: '[subject]', if: new SourceClass(CarePlanDetails::class))]
    public array $subject;

    #[Map(source: '[encounter]', if: new SourceClass(CarePlanDetails::class))]
    public array $encounterReference;

    #[Map(source: '[managing_organization]', if: new SourceClass(CarePlanDetails::class))]
    public array $managingOrganizationReference;

    #[Map(source: '[based_on?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $basedOn = null;

    #[Map(source: '[part_of?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $partOf = null;

    #[Map(source: '[care_manager?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $careManager = null;

    #[Map(source: '[supporting_info?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $supportingInfo = null;

    #[Map(source: '[contributor?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $contributor = null;

    #[Map(source: '[inform_with?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $informWith = null;

    #[Map(source: '[status_history]', if: new SourceClass(CarePlanDetails::class))]
    public array $statusHistory;

    #[Map(source: '[inserted_at]', if: new SourceClass(CarePlanDetails::class))]
    public string $ehealthInsertedAt;

    #[Map(source: '[inserted_by]', if: new SourceClass(CarePlanDetails::class))]
    public string $ehealthInsertedBy;

    #[Map(source: '[updated_at]', if: new SourceClass(CarePlanDetails::class))]
    public string $ehealthUpdatedAt;

    #[Map(source: '[updated_by]', if: new SourceClass(CarePlanDetails::class))]
    public string $ehealthUpdatedBy;

    public IdentifierData $authorReference;
    public IdentifierData $subjectReference;
    public IdentifierData $encounterIdentifier;
    public IdentifierData $managingOrganization;

    /** @var Collection<int, IdentifierData> */
    public Collection $supportingInfoReferences;

    /** @var array<string, string> */
    public array $medicalRecordLabels;

    /** @var Collection<int, IdentifierData> */
    public Collection $contributors;

    /**
     * Project a full response to the existing resource contract for storage and reconciliation.
     *
     * @return array<string, mixed>
     */
    public function toResource(): array
    {
        return Arr::toCamelCase([
            'id' => $this->uuid,
            'status' => $this->status,
            'intent' => $this->intent,
            'title' => $this->title,
            'context' => $this->context,
            'description' => $this->description,
            'note' => $this->note,
            'period' => ['start' => $this->periodStart, 'end' => $this->periodEnd],
            'addresses' => $this->addresses,
            'category' => $this->categoryConcept->toArray(),
            'termsOfService' => $this->termsOfServiceConcept->toArray(),
            'statusReason' => $this->statusReasonConcept?->toArray(),
            'requisition' => $this->requisition,
            'author' => $this->author,
            'subject' => $this->subject,
            'encounter' => $this->encounterReference,
            'managingOrganization' => $this->managingOrganizationReference,
            'basedOn' => $this->basedOn,
            'partOf' => $this->partOf,
            'careManager' => $this->careManager,
            'supportingInfo' => $this->supportingInfo,
            'contributor' => $this->contributor,
            'informWith' => $this->informWith,
            'statusHistory' => $this->statusHistory,
            'insertedAt' => $this->ehealthInsertedAt,
            'insertedBy' => $this->ehealthInsertedBy,
            'updatedAt' => $this->ehealthUpdatedAt,
            'updatedBy' => $this->ehealthUpdatedBy,
        ]);
    }

    /**
     * Map validated local selections into the relations stored with the draft.
     *
     * @param list<string> $coAuthors
     * @param list<string> $episodeUuids
     * @param array<string, string> $medicalRecordLabels
     */
    public function prepareLocal(
        string $authorUuid,
        string $patientUuid,
        string $legalEntityUuid,
        array $coAuthors,
        array $episodeUuids,
        array $medicalRecordLabels,
    ): void {
        $this->categoryConcept = CodeableConceptData::map([
            'coding' => [['system' => 'eHealth/care_plan_categories', 'code' => $this->category]],
        ]);
        $this->termsOfServiceConcept = CodeableConceptData::map([
            'coding' => [['system' => 'PROVIDING_CONDITION', 'code' => $this->termsOfService]],
        ]);
        $this->authorReference = $this->identifier($authorUuid, ResourceType::EMPLOYEE->value);
        $this->subjectReference = $this->identifier($patientUuid, 'patient');
        $this->encounterIdentifier = $this->identifier($this->encounter, 'encounter');
        $this->managingOrganization = $this->identifier($legalEntityUuid, ResourceType::LEGAL_ENTITY->value);
        $this->medicalRecordLabels = $medicalRecordLabels;
        $this->contributors = collect($coAuthors)->map(
            fn (string $uuid): IdentifierData => $this->identifier($uuid, ResourceType::EMPLOYEE->value)
        );
        $this->supportingInfoReferences = collect($episodeUuids)->map(
            fn (string $uuid): IdentifierData => $this->identifier($uuid, 'episode_of_care')
        )->concat(collect(array_keys($medicalRecordLabels))->map(function (string $key): IdentifierData {
            [$type, $uuid] = explode(':', $key, 2);

            return $this->identifier($uuid, $type);
        }));
        foreach (['context', 'description', 'note'] as $field) {
            $this->{$field} = $this->{$field} === '' ? null : $this->{$field};
        }
    }

    protected function identifier(string $uuid, string $type): IdentifierData
    {
        return IdentifierData::map([
            'value' => $uuid,
            'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => $type]]],
        ]);
    }
}
