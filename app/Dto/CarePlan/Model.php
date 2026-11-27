<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Core\Arr;
use App\Dto\CodeableConcept\Model as CodeableConceptData;
use App\Dto\Identifier\Model as IdentifierData;
use App\Dto\Transform\Model\Reference;
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
 * through attribute access instead of declared properties. The concepts, identifiers
 * and period are built from the form while mapping, so no value is assigned afterwards.
 */
#[Map(source: CarePlanDetails::class)]
#[Map(source: CarePlanForm::class)]
#[Map(target: CarePlan::class)]
class Model
{
    #[Map(source: '[id]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'uuid', if: new SourceClass(CarePlanForm::class))]
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
    #[Map(source: 'addresses', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public array $addresses = [];

    #[Map(source: '[category]', if: new SourceClass(CarePlanDetails::class), transform: [CodeableConceptData::class, 'map'])]
    #[Map(source: 'category', if: new SourceClass(CarePlanForm::class), transform: [self::class, 'category'])]
    public CodeableConceptData $categoryConcept;

    #[Map(source: '[terms_of_service]', if: new SourceClass(CarePlanDetails::class), transform: [CodeableConceptData::class, 'map'])]
    #[Map(source: 'termsOfService', if: new SourceClass(CarePlanForm::class), transform: [self::class, 'termsOfService'])]
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

    #[Map(source: 'authorUuid', if: new SourceClass(CarePlanForm::class), transform: new Reference(ResourceType::EMPLOYEE))]
    public IdentifierData $authorReference;

    #[Map(source: 'patientUuid', if: new SourceClass(CarePlanForm::class), transform: new Reference(ResourceType::PATIENT))]
    public IdentifierData $subjectReference;

    #[Map(source: 'encounter', if: new SourceClass(CarePlanForm::class), transform: new Reference(ResourceType::ENCOUNTER))]
    public IdentifierData $encounterIdentifier;

    #[Map(source: 'legalEntityUuid', if: new SourceClass(CarePlanForm::class), transform: new Reference(ResourceType::LEGAL_ENTITY))]
    public IdentifierData $managingOrganization;

    /** @var Collection<int, IdentifierData> */
    #[Map(source: 'episodes', if: new SourceClass(CarePlanForm::class), transform: [self::class, 'supportingInfo'])]
    public Collection $supportingInfoReferences;

    /** @var array<string, string> */
    #[Map(source: 'medicalRecordLabels', if: new SourceClass(CarePlanForm::class))]
    public array $medicalRecordLabels = [];

    /** @var Collection<int, IdentifierData> */
    #[Map(source: 'coAuthors', if: new SourceClass(CarePlanForm::class), transform: [self::class, 'contributors'])]
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
     * Care plan category code from the form as a codeable concept of the care plan categories dictionary.
     *
     * @param  string  $category
     * @return CodeableConceptData
     */
    public static function category(string $category): CodeableConceptData
    {
        return CodeableConceptData::map(['coding' => [['system' => 'eHealth/care_plan_categories', 'code' => $category]]]);
    }

    /**
     * Terms of service code from the form as a codeable concept of the providing condition dictionary.
     *
     * @param  string  $termsOfService
     * @return CodeableConceptData
     */
    public static function termsOfService(string $termsOfService): CodeableConceptData
    {
        return CodeableConceptData::map(['coding' => [['system' => 'PROVIDING_CONDITION', 'code' => $termsOfService]]]);
    }

    /**
     * Co-author UUIDs from the form as identifiers of employees, skipping the empty and repeated ones.
     * The form is passed along with the mapped value, since a transform receives the source too.
     *
     * @param  array  $coAuthors
     * @param  CarePlanForm  $form
     * @return Collection<int, IdentifierData>
     */
    public static function contributors(array $coAuthors, CarePlanForm $form): Collection
    {
        $reference = new Reference(ResourceType::EMPLOYEE);

        return collect($coAuthors)->filter()->unique()->values()->map(
            static fn (string $uuid): IdentifierData => $reference($uuid, $form, null)
        );
    }

    /**
     * Attached episodes and medical records from the form as identifiers of them.
     * The medical records are read from the form, and each of them names its own resource type.
     *
     * @param  array  $episodes
     * @param  CarePlanForm  $form
     * @return Collection<int, IdentifierData>
     */
    public static function supportingInfo(array $episodes, CarePlanForm $form): Collection
    {
        $episodeReferences = collect($episodes)->map(
            static fn (array $episode): IdentifierData => (new Reference(ResourceType::EPISODE_OF_CARE))($episode['uuid'], $form, null)
        );

        $recordReferences = collect($form->medicalRecords)->mapWithKeys(static function (array $record) use ($form): array {
            $type = $record['type'] ?? data_get($record, 'identifier.type.coding.0.code');
            $uuid = $record['uuid'] ?? data_get($record, 'identifier.value');

            return ["$type:$uuid" => (new Reference(ResourceType::from($type)))($uuid, $form, null)];
        });

        return $episodeReferences->values()->concat($recordReferences->values());
    }
}
