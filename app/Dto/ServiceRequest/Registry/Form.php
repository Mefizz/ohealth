<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Registry;

use App\Enums\Person\ServiceRequestStatus;
use App\Models\MedicalEvents\Sql\CodeableConcept;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Presentation data only; dictionary and relationship selection remain with the repository. */
#[Map(source: ServiceRequestRequest::class)]
class Form
{
    #[Map(source: '[id?]')]
    public ?int $id = null;

    #[Map(source: '[uuid?]')]
    public ?string $uuid = null;

    #[Map(source: '[status?]')]
    public ?string $status = null;

    #[Map(source: '[startedAt?]')]
    public ?Carbon $startedAt = null;

    #[Map(source: '[endedAt?]')]
    public ?Carbon $endedAt = null;

    #[Map(source: '[quantity?]')]
    public ?string $quantity = null;

    #[Map(source: '[category?]')]
    public ?CodeableConcept $category = null;

    #[Map(source: '[priority?]')]
    public ?CodeableConcept $priority = null;

    #[Map(source: '[serviceId?]')]
    public ?string $serviceId = null;

    #[Map(source: '[resourceMetadata?][code?][displayValue?]')]
    public ?string $serviceDisplayValue = null;

    #[Map(source: '[basedOn?]')]
    public ?Identifier $basedOn = null;

    #[Map(source: '[context?]')]
    public ?Identifier $context = null;

    #[Map(source: '[programId?]')]
    public ?string $programId = null;

    #[Map(source: '[resourceMetadata?][program?][displayValue?]')]
    public ?string $programDisplayValue = null;

    #[Map(source: '[programReference?]')]
    public ?Identifier $programReference = null;

    #[Map(source: '[requestNumber?]')]
    public ?string $requestNumber = null;

    #[Map(source: '[note?]')]
    public ?string $note = null;

    #[Map(source: '[patientInstruction?]')]
    public ?string $patientInstruction = null;

    /**
     * @param  array<string, object>  $carePlanIdsByActivityUuid
     * @param  array<string, int>  $encounterIdsByUuid
     * @return array<string, mixed>
     */
    public function toArray(
        array $carePlanIdsByActivityUuid = [],
        array $encounterIdsByUuid = [],
        bool $pending = false,
        bool $canComplete = false,
        array $serviceNames = [],
        array $programNames = []
    ): array {
        $status = strtolower((string) $this->status);
        $startedAt = $this->startedAt;
        $endedAt = $this->endedAt;
        $qty = $this->quantity ?? '';
        $qtyLabel = str_contains($qty, '.') ? rtrim(rtrim($qty, '0'), '.') : $qty;

        $category = strtolower((string) ($this->category?->coding?->first()?->code ?? ($this->category?->text ?? '')));
        $categoryKey = 'care-plan.referral_category.'.match ($category) {
            'counselling' => 'counseling',
            'laboratory' => 'laboratory_procedure',
            'transfer_of_care' => 'transfer',
            default => $category,
        };
        $categoryLabel = $category !== '' && Lang::has($categoryKey)
            ? __($categoryKey)
            : ($category !== '' ? $category : '—');

        $priority = strtolower((string) ($this->priority?->text ?? ''));
        $priorityKey = 'care-plan.referral_priority.'.$priority;
        $priorityLabel = $priority !== '' && Lang::has($priorityKey)
            ? __($priorityKey)
            : ($priority !== '' ? $priority : '—');

        $serviceId = (string) ($this->serviceId ?? '');
        $itemName = $serviceId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $serviceId) !== 1
            ? $serviceId
            : (($serviceNames[$serviceId] ?? null) ?: (filled($this->serviceDisplayValue) ? $this->serviceDisplayValue : $categoryLabel));

        $activityUuid = $this->basedOn?->value;
        $encounterUuid = $this->context?->value;
        $activityData = $activityUuid ? ($carePlanIdsByActivityUuid[$activityUuid] ?? null) : null;
        $activityId = $activityData ? $activityData->id : null;
        $carePlanId = $activityData ? $activityData->care_plan_id : null;
        $encounterId = $encounterUuid ? ($encounterIdsByUuid[$encounterUuid] ?? null) : null;

        $basisLabel = match (true) {
            $activityId !== null && $activityId > 0 => __('care-plan.care_plan'),
            $encounterId !== null && $encounterId > 0 => __('encounters.label'),
            default => '—',
        };

        $periodLabel = '—';
        if ($startedAt !== null && $endedAt !== null) {
            $periodLabel = $startedAt->format('d.m.Y').' — '.$endedAt->format('d.m.Y');
        } elseif ($startedAt !== null) {
            $periodLabel = __('referrals.registry.period_from', ['date' => $startedAt->format('d.m.Y')]);
        }

        $draftStatuses = [
            ServiceRequestStatus::DRAFT->value,
            ServiceRequestStatus::NEW->value,
        ];

        return [
            'id' => $this->id,
            'uuid' => (string) $this->uuid,
            'kind' => 'service_request',
            'requestNumber' => trim((string) ($this->requestNumber ?? '')),
            'status' => (string) $this->status,
            'statusLabel' => ServiceRequestStatus::labelFor($status),
            'statusBadge' => ServiceRequestStatus::colorFor($status),
            'itemName' => $itemName !== '' && $itemName !== '—' ? $itemName : __('forms.service'),
            'quantity' => $qtyLabel !== '' ? $qtyLabel : '—',
            'startedAt' => $startedAt?->toDateString(),
            'endedAt' => $endedAt?->toDateString(),
            'periodLabel' => $periodLabel,
            'programName' => $this->displayProgramName($programNames),
            'categoryLabel' => $categoryLabel,
            'priorityLabel' => $priorityLabel,
            'note' => (string) ($this->note ?? ''),
            'patientInstruction' => (string) ($this->patientInstruction ?? ''),
            'basisLabel' => $basisLabel,
            'encounterId' => $encounterId,
            'activityId' => $activityId,
            'carePlanId' => $carePlanId,
            'submissionPending' => $pending,
            'canSign' => !$pending && in_array($status, $draftStatuses, true),
            'canOperate' => !$pending && $status === ServiceRequestStatus::ACTIVE->value,
            'canRecall' => !$pending && $status === ServiceRequestStatus::ACTIVE->value,
            'canCancel' => !$pending && $status === ServiceRequestStatus::ACTIVE->value,
            'canComplete' => !$pending && $canComplete,
        ];
    }

    protected function displayProgramName(array $programNames): string
    {
        $value = trim((string) ($this->programId ?? ''));
        $display = trim((string) ($this->programReference?->displayValue ?? $this->programDisplayValue));
        if ($display !== '' && strtolower($display) !== 'null') {
            return $display;
        }

        return $value === '' ? __('referrals.registry.program_not_specified') : ($programNames[$value] ?? $value);
    }
}
