<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Dto\CodeableConcept\Model as Concept;
use App\Dto\Input\FormCodeableConcept;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Models\CarePlan;
use Carbon\CarbonImmutable;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\ObjectMapper\Condition\TargetClass;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

/** Form or validated full CarePlan projected directly into an existing SQL model. */
#[Map(source: CarePlanForm::class)]
#[Map(source: CarePlanDetails::class)]
#[Map(target: CarePlan::class)]
class Model
{
    #[Map(source: '[id?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $uuid = null;

    #[Map(source: '[status?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public string $status = 'draft';

    #[Map(source: '[title?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'title', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $title = null;

    #[Map(source: '[intent?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'intent', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public string $intent = 'order';

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

    #[Map(source: '[requisition?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $requisition = null;

    #[Map(source: '[inform_with?][auth_method_id?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(source: 'informWith', if: new SourceClass(CarePlanForm::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $informWith = null;

    #[Map(source: '[status_history?]', if: new IsNotNull())]
    #[Map(if: new TargetClass(CarePlan::class))]
    public array $statusHistory = [];

    #[Map(source: '[inserted_at?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $ehealthInsertedAt = null;

    #[Map(source: '[inserted_by?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $ehealthInsertedBy = null;

    #[Map(source: '[updated_at?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $ehealthUpdatedAt = null;

    #[Map(source: '[updated_by?]', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?string $ehealthUpdatedBy = null;

    #[Map(source: 'native', if: new SourceClass(CarePlanDetails::class))]
    #[Map(if: new TargetClass(CarePlan::class))]
    public ?array $ehealthPayload = null;

    #[Map(source: '[category?]', if: new SourceClass(CarePlanDetails::class), transform: [Concept::class, 'map'])]
    public ?Concept $category = null;

    #[Map(source: '[terms_of_service?]', if: new SourceClass(CarePlanDetails::class), transform: [Concept::class, 'map'])]
    public ?Concept $termsOfService = null;

    #[Map(source: '[status_reason?]', if: new SourceClass(CarePlanDetails::class), transform: [Concept::class, 'map'])]
    public ?Concept $statusReason = null;

    /** @var list<Concept> */
    #[Map(source: '[addresses?]', if: new IsNotNull(), transform: new MapCollection(targetClass: Concept::class))]
    public array $addresses = [];

    #[Map(source: '[period?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $period = null;

    #[Map(source: '[author?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $author = null;

    #[Map(source: '[encounter?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $encounter = null;

    #[Map(source: '[subject?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $subject = null;

    #[Map(source: '[managing_organization?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $managingOrganization = null;

    #[Map(source: '[based_on?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $basedOn = null;

    #[Map(source: '[part_of?]', if: new SourceClass(CarePlanDetails::class))]
    public ?array $partOf = null;

    #[Map(source: '[supporting_info?]', if: new IsNotNull())]
    public array $supportingInfo = [];

    #[Map(source: '[contributor?]', if: new IsNotNull())]
    public array $contributor = [];

    #[Map(source: 'category', if: new SourceClass(CarePlanForm::class))]
    public string $categoryCode = '';

    #[Map(source: 'termsOfService', if: new SourceClass(CarePlanForm::class))]
    public string $termsOfServiceCode = '';

    #[Map(source: 'encounter', if: new SourceClass(CarePlanForm::class))]
    public string $encounterUuid = '';

    #[Map(source: 'periodStart', if: new SourceClass(CarePlanForm::class))]
    public string $periodStart = '';

    #[Map(source: 'periodStartTime', if: new SourceClass(CarePlanForm::class))]
    public string $periodStartTime = '';

    #[Map(source: 'periodEnd', if: new SourceClass(CarePlanForm::class))]
    public string $periodEnd = '';

    #[Map(source: 'periodEndTime', if: new SourceClass(CarePlanForm::class))]
    public string $periodEndTime = '';

    /** Prepare local clinical relations after the component supplies authorized identities and selections. */
    public function prepareLocal(
        ObjectMapperInterface $mapper,
        string $patientUuid,
        string $authorUuid,
        string $legalEntityUuid,
        array $addresses,
        array $coAuthors,
        array $episodes,
        array $medicalRecords,
    ): void {
        $this->category = $mapper->map(new FormCodeableConcept([
            'coding' => [['system' => 'eHealth/care_plan_categories', 'code' => $this->categoryCode]],
        ]), Concept::class);
        $this->termsOfService = $mapper->map(new FormCodeableConcept([
            'coding' => [['system' => 'PROVIDING_CONDITION', 'code' => $this->termsOfServiceCode]],
        ]), Concept::class);
        $this->addresses = array_map(
            fn (array $address): Concept => $mapper->map(new FormCodeableConcept($address), Concept::class),
            $addresses
        );
        $this->author = $this->reference($authorUuid, 'employee');
        $this->subject = $this->reference($patientUuid, 'patient');
        $this->encounter = $this->reference($this->encounterUuid, 'encounter');
        $this->managingOrganization = $this->reference($legalEntityUuid, 'legal_entity');
        $this->period = [
            'start' => $this->instant($this->periodStart, $this->periodStartTime),
            'end' => $this->periodEnd === '' ? null : $this->instant($this->periodEnd, $this->periodEndTime),
        ];
        $this->contributor = array_map(fn (string $uuid): array => $this->reference($uuid, 'employee'), $coAuthors);
        $this->supportingInfo = [
            ...array_map(fn (string $uuid): array => $this->reference($uuid, 'episode_of_care'), $episodes),
            ...array_map(fn (array $record): array => $this->reference($record['uuid'], $record['type']), $medicalRecords),
        ];
        $this->description = $this->description === '' ? null : $this->description;
        $this->note = $this->note === '' ? null : $this->note;
        $this->informWith = $this->informWith === '' ? null : $this->informWith;
    }

    protected function reference(string $uuid, string $type): array
    {
        return ['identifier' => [
            'value' => $uuid,
            'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => $type]]],
        ]];
    }

    protected function instant(string $date, string $time): string
    {
        return CarbonImmutable::createFromFormat(
            '!'.config('app.date_format').' H:i', $date.' '.($time === '' ? '00:00' : $time), config('app.timezone')
        )->utc()->toIso8601String();
    }
}
