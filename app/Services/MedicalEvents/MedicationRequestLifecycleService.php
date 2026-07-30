<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\Api\MedicationRequest;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\MedicalEvents\Concerns\ResolvesEmployeeContext;
use Illuminate\Support\Str;

class MedicationRequestLifecycleService
{
    use ResolvesEmployeeContext;

    /**
     * PreQualify Medication Request.
     *
     * @param  array  $payload
     * @return array
     */
    public function preQualify(array $payload): array
    {
        try {
            $response = MedicationRequest::preQualify($payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Prequalify failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create Medication Request (Draft).
     */
    public function createDraft(mixed $carePlanOrPayload, mixed $activity = null, array $formData = [], array $employeeContext = []): array|string
    {
        if (is_array($carePlanOrPayload)) {
            try {
                $response = MedicationRequest::createMedicationRequest($carePlanOrPayload);

                return $response['data'] ?? $response;
            } catch (Exception $e) {
                Log::error('ePrescription Create Draft failed: ' . $e->getMessage());
                throw $e;
            }
        }

        $carePlan = $carePlanOrPayload;

        $dbData = [
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employeeContext['employee_id'] ?? null,
            'person_id' => $carePlan->person_id,
            'division_id' => $employeeContext['division_id'] ?? null,
            'status' => 'draft',
            'started_at' => $formData['started_at'] ?? now()->toDateString(),
            'ended_at' => $formData['ended_at'] ?? now()->addDays(30)->toDateString(),
            'medication_id' => $formData['medication_id'] ?? null,
            'medication_qty' => (float) ($formData['medication_qty'] ?? 1),
            'medication_program_id' => $formData['program_id'] ?? null,
            'intent' => 'order',
            'category' => 'community',
            'based_on_id' => $activity ? $activity->id : null,
            'context_id' => $carePlan->encounter?->id ?? null,
            'based_on_uuid' => $activity ? $activity->uuid : null,
            'dosage_instructions' => [
                [
                    'sequence' => 1,
                    'text' => $formData['signature_text'] ?? 'За призначенням лікаря',
                    'dose_and_rate' => [
                        [
                            'dose_quantity_value' => (float) ($formData['max_dose_per_administration'] ?? 1.0),
                            'dose_quantity_unit' => $formData['medication_unit'] ?? 'од.',
                        ]
                    ],
                    'max_dose_per_administration' => (float) ($formData['max_dose_per_administration'] ?? 1.0),
                    'max_dose_per_period' => (float) ($formData['max_dose_per_period'] ?? 1.0),
                ]
            ],
            'inform_with' => $formData['inform_with'] ?? null,
        ];

        $uuids = [
            'person_uuid' => $carePlan->person->uuid,
            'encounter_uuid' => $carePlan->encounter?->uuid ?? null,
            'episode_uuid' => $carePlan->episode_id,
            'employee_uuid' => $employeeContext['employee_uuid'] ?? null,
            'legal_entity_uuid' => $employeeContext['legal_entity_uuid'] ?? null,
            'division_uuid' => $employeeContext['division_id'] ? \App\Models\Division::find($employeeContext['division_id'])?->uuid : null,
        ];

        $mapper = new \App\Services\MedicalEvents\Mappers\MedicationRequestMapper();

        if (!empty($dbData['medication_program_id'])) {
            $prequalifyPayload = $mapper->toCreateRequestPayload($dbData, $uuids, $carePlan->uuid);
            $response = MedicationRequest::preQualify($prequalifyPayload);

            if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
                $finalResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($response);
                app(\App\Services\MedicalEvents\EHealthJobResolver::class)->assertPrequalifyValid($finalResponse);
            }
        }

        \App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest::create($dbData);

        return $dbData['uuid'];
    }

    /**
     * Sign Medication Request.
     */
    public function sign(mixed $idOrCarePlan, mixed $payloadOrRequestRecord, array $formData = [], string $informWith = '', float $remainingQty = 0.0): array
    {
        if (is_string($idOrCarePlan) && is_array($payloadOrRequestRecord)) {
            try {
                $response = MedicationRequest::signMedicationRequest($idOrCarePlan, $payloadOrRequestRecord);

                return $response['data'] ?? $response;
            } catch (Exception $e) {
                Log::error('ePrescription Sign failed: ' . $e->getMessage());
                throw $e;
            }
        }

        $requestRecord = $payloadOrRequestRecord;

        $payload = [
            'signed_content' => $formData['signed_content'] ?? null,
            'signed_content_encoding' => 'base64',
        ];

        $response = MedicationRequest::signMedicationRequest($requestRecord->uuid, $payload);

        if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
            $finalResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($response);
            $requestRecord->update(['status' => 'active']);

            return $finalResponse->getData() ?? [];
        }

        return $response['data'] ?? $response;
    }

    /**
     * Reject Medication Request (ACTIVE).
     */
    public function reject(mixed $idOrCarePlan, mixed $payloadOrRequestRecord = [], array $formData = [], string $statusReason = ''): array
    {
        if (is_string($idOrCarePlan) && is_array($payloadOrRequestRecord)) {
            try {
                $response = MedicationRequest::rejectMedicationRequest($idOrCarePlan, $payloadOrRequestRecord);

                return $response['data'] ?? $response;
            } catch (Exception $e) {
                Log::error('ePrescription Reject failed: ' . $e->getMessage());
                throw $e;
            }
        }

        $requestRecord = $payloadOrRequestRecord;

        if ($requestRecord->status === 'draft') {
            $requestRecord->update(['status' => 'entered_in_error']);

            return [];
        }

        $payload = [
            'signed_content' => $formData['signed_content'] ?? null,
            'signed_content_encoding' => 'base64',
        ];

        $response = MedicationRequest::rejectMedicationRequest($requestRecord->uuid, $payload);

        if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
            $finalResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($response);
            $requestRecord->update(['status' => 'entered_in_error']);

            return $finalResponse->getData() ?? [];
        }

        return $response['data'] ?? $response;
    }

    /**
     * Reject un-signed Medication Request (NEW).
     */
    public function rejectDraft(string $id, array $payload): array
    {
        try {
            $response = MedicationRequest::rejectUnsignedMedicationRequest($id, $payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Reject Draft failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
