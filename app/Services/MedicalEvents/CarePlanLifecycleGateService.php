<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\MedicalEvents\Sql\DeviceRequestRequest;
use App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use Illuminate\Support\Collection;

/**
 * TV 3.10.4 pre-checks before cancel/complete of care plan activities and plan cancel.
 */
class CarePlanLifecycleGateService
{
    /**
     * Terminal / closed document statuses that do not block activity cancel/complete.
     *
     * @var list<string>
     */
    public const CLOSED_DOCUMENT_STATUSES = [
        'completed',
        'cancelled',
        'canceled',
        'rejected',
        'expired',
        'entered-in-error',
        'entered_in_error',
        'recalled',
        'revoked',
        'stopped',
    ];

    /**
     * Activity statuses that block care plan cancel (TV 3.10.4.5.1).
     *
     * @var list<string>
     */
    public const PLAN_CANCEL_BLOCKING_ACTIVITY_STATUSES = [
        'scheduled',
        'in_progress',
        'in-progress',
        'completed',
    ];

    /**
     * @return list<array{type: string, status: string, uuid: string|null}>
     */
    public function findOpenDocumentsForActivity(CarePlanActivity $activity): array
    {
        $open = [];

        foreach ($this->openMedicationDocuments($activity) as $row) {
            $open[] = $row;
        }

        foreach ($this->openServiceDocuments($activity) as $row) {
            $open[] = $row;
        }

        foreach ($this->openDeviceDocuments($activity) as $row) {
            $open[] = $row;
        }

        return $open;
    }

    public function hasOpenDocumentsForActivity(CarePlanActivity $activity): bool
    {
        return $this->findOpenDocumentsForActivity($activity) !== [];
    }

    /**
     * Ukrainian blocking message for activity cancel/complete, or null when allowed.
     */
    public function activityStatusChangeBlockReason(CarePlanActivity $activity, string $action): ?string
    {
        $open = $this->findOpenDocumentsForActivity($activity);
        if ($open === []) {
            return null;
        }

        $reasons = collect($open)
            ->map(fn (array $doc): string => $this->describeOpenDocument($doc))
            ->unique()
            ->values()
            ->all();

        $key = $action === 'complete_activity'
            ? 'care-plan.cannot_complete_activity_open_docs'
            : 'care-plan.cannot_cancel_activity_open_docs';

        return __($key, ['reasons' => implode('; ', $reasons)]);
    }

    /**
     * Activities that block plan cancel (scheduled / in_progress / completed).
     *
     * @return Collection<int, CarePlanActivity>
     */
    public function findActivitiesBlockingPlanCancel(CarePlan $carePlan): Collection
    {
        $carePlan->loadMissing('activities');

        return $carePlan->activities
            ->filter(function (CarePlanActivity $activity): bool {
                $status = strtolower((string) $activity->status);

                return in_array($status, self::PLAN_CANCEL_BLOCKING_ACTIVITY_STATUSES, true);
            })
            ->values();
    }

    public function planCancelBlockReason(CarePlan $carePlan): ?string
    {
        $blocking = $this->findActivitiesBlockingPlanCancel($carePlan);
        if ($blocking->isEmpty()) {
            return null;
        }

        $statuses = $blocking
            ->map(fn (CarePlanActivity $activity): string => strtolower((string) $activity->status))
            ->unique()
            ->map(fn (string $status): string => __('care-plan.status.'.$this->normalizeStatusKey($status)))
            ->values()
            ->all();

        return __('care-plan.cannot_cancel_plan_blocking_activities', [
            'statuses' => implode(', ', $statuses),
        ]);
    }

    /**
     * @return list<array{type: string, status: string, uuid: string|null}>
     */
    private function openMedicationDocuments(CarePlanActivity $activity): array
    {
        return MedicationRequestRequest::query()
            ->where('based_on_id', $activity->id)
            ->get(['uuid', 'status'])
            ->filter(fn (MedicationRequestRequest $row): bool => $this->isOpenDocumentStatus((string) $row->status))
            ->map(fn (MedicationRequestRequest $row): array => [
                'type' => $this->medicationDocumentType((string) $row->status),
                'status' => strtolower((string) $row->status),
                'uuid' => $row->uuid,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{type: string, status: string, uuid: string|null}>
     */
    private function openServiceDocuments(CarePlanActivity $activity): array
    {
        return ServiceRequestRequest::query()
            ->where('based_on_id', $activity->id)
            ->get(['uuid', 'status'])
            ->filter(fn (ServiceRequestRequest $row): bool => $this->isOpenDocumentStatus((string) $row->status))
            ->map(fn (ServiceRequestRequest $row): array => [
                'type' => 'service_request',
                'status' => strtolower((string) $row->status),
                'uuid' => $row->uuid,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{type: string, status: string, uuid: string|null}>
     */
    private function openDeviceDocuments(CarePlanActivity $activity): array
    {
        return DeviceRequestRequest::query()
            ->where('based_on_id', $activity->id)
            ->get(['uuid', 'status'])
            ->filter(fn (DeviceRequestRequest $row): bool => $this->isOpenDocumentStatus((string) $row->status))
            ->map(fn (DeviceRequestRequest $row): array => [
                'type' => 'device_request',
                'status' => strtolower((string) $row->status),
                'uuid' => $row->uuid,
            ])
            ->values()
            ->all();
    }

    private function isOpenDocumentStatus(string $status): bool
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return true;
        }

        return !in_array($normalized, self::CLOSED_DOCUMENT_STATUSES, true);
    }

    private function medicationDocumentType(string $status): string
    {
        $normalized = strtolower($status);

        // Local MRR before sign; after sign the same table holds ACTIVE MR.
        if (in_array($normalized, ['new', 'draft', 'signed'], true)) {
            return 'medication_request_request';
        }

        return 'medication_request';
    }

    /**
     * @param  array{type: string, status: string, uuid: string|null}  $doc
     */
    private function describeOpenDocument(array $doc): string
    {
        $typeLabel = match ($doc['type']) {
            'medication_request_request' => __('care-plan.open_doc_type.medication_request_request'),
            'medication_request' => __('care-plan.open_doc_type.medication_request'),
            'service_request' => __('care-plan.open_doc_type.service_request'),
            'device_request' => __('care-plan.open_doc_type.device_request'),
            default => $doc['type'],
        };

        $statusLabel = __('care-plan.status.'.$this->normalizeStatusKey($doc['status']));

        return $typeLabel.' ('.$statusLabel.')';
    }

    private function normalizeStatusKey(string $status): string
    {
        return str_replace('-', '_', strtolower($status));
    }
}
