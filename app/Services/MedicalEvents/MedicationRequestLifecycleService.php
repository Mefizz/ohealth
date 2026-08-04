<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\Api\MedicationRequest;
use App\Repositories\MedicalEvents\MedicationRequestRepository;
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

        $activeEncounter = \App\Models\MedicalEvents\Sql\Encounter::query()
            ->where('person_id', $carePlan->person_id)
            ->where('status', 'finished')
            ->whereDate('created_at', today())
            ->latest('id')
            ->first();

        if (!$activeEncounter) {
            throw new \Exception('Для виписування рецепту необхідно мати збережену взаємодію з пацієнтом. Будь ласка, створіть взаємодію сьогодні перед виписуванням рецепту.');
        }

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
            'context_id' => $activeEncounter->id,
            'based_on_uuid' => $activity ? $activity->uuid : null,
            'container_dosage' => $formData['container_dosage'] ?? null,
            'note' => $formData['note'] ?? null,
            'dosage_instructions' => [
                [
                    'sequence' => 1,
                    'text' => !empty($formData['signature_text']) ? $formData['signature_text'] : 'За призначенням лікаря',
                    'patient_instruction' => !empty($formData['patient_instruction']) ? $formData['patient_instruction'] : (!empty($formData['signature_text']) ? $formData['signature_text'] : 'За призначенням лікаря'),
                    'route' => $formData['route'] ?? 'oral',
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
            'encounter_uuid' => $activeEncounter->uuid,
            'episode_uuid' => $carePlan->episode_id,
            'employee_uuid' => $employeeContext['employee_uuid'] ?? null,
            'legal_entity_uuid' => $employeeContext['legal_entity_uuid'] ?? null,
            'division_uuid' => $employeeContext['division_id'] ? \App\Models\Division::find($employeeContext['division_id'])?->uuid : null,
        ];

        $mapper = new \App\Services\MedicalEvents\Mappers\MedicationRequestMapper();

        if (!empty($dbData['medication_program_id'])) {
            $prequalifyPayload = $mapper->toPrequalifyPayload($dbData, $uuids, $carePlan->uuid);
            $response = MedicationRequest::preQualify($prequalifyPayload);

            if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
                $finalResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($response);
                app(\App\Services\MedicalEvents\EHealthJobResolver::class)->assertPrequalifyValid($finalResponse);
            }
        }

        $createPayload = $mapper->toCreateRequestPayload($dbData, $uuids, $carePlan->uuid);
        Log::debug('ePrescription Create Request Payload:', $createPayload);
        $createResponse = MedicationRequest::createMedicationRequest($createPayload);

        if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
            $finalCreateResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($createResponse);
            Log::debug('ePrescription Create Response from eHealth:', ['response' => $createResponse, 'resolved' => $finalCreateResponse]);
            if (($finalCreateResponse['status'] ?? null) === 'failed') {
                throw new \App\Exceptions\EHealth\EHealthValidationException($finalCreateResponse);
            }
            $dbData['request_number'] = $finalCreateResponse['request_number'] ?? ($finalCreateResponse['requisition'] ?? ($finalCreateResponse['data']['request_number'] ?? null));
            $dbData['uuid'] = $finalCreateResponse['id'] ?? ($finalCreateResponse['data']['id'] ?? $dbData['uuid']);
            $payloadToStore = $finalCreateResponse['data'] ?? (isset($finalCreateResponse['person']) || isset($finalCreateResponse['based_on']) ? $finalCreateResponse : ($createResponse['data'] ?? (isset($createResponse['person']) || isset($createResponse['based_on']) ? $createResponse : $finalCreateResponse)));
            $dbData['ehealth_payload'] = is_array($payloadToStore) ? $payloadToStore : (array) $payloadToStore;
        } else {
            Log::debug('ePrescription Create Response from eHealth:', ['response' => $createResponse]);
            $dbData['request_number'] = $createResponse['request_number'] ?? ($createResponse['data']['request_number'] ?? null);
            $dbData['uuid'] = $createResponse['id'] ?? ($createResponse['data']['id'] ?? $dbData['uuid']);
            $payloadToStore = $createResponse['data'] ?? (isset($createResponse['person']) || isset($createResponse['based_on']) ? $createResponse : $createResponse);
            $dbData['ehealth_payload'] = is_array($payloadToStore) ? $payloadToStore : (array) $payloadToStore;
        }

        app(MedicationRequestRepository::class)->store($dbData, (int) $carePlan->person_id);

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

        $signedContent = $formData['signed_medication_request_request'] ?? ($formData['signed_content'] ?? ($formData['signed_data'] ?? null));

        if (empty($signedContent) && isset($formData['password'], $formData['knedp']) && $idOrCarePlan instanceof \App\Models\CarePlan && $requestRecord instanceof \App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest) {
            $signPayload = $this->buildSignPayload($idOrCarePlan, $requestRecord, $informWith);

            $signedContent = signatureService()->signData(
                $signPayload,
                $formData['password'],
                $formData['knedp'],
                $formData['keyContainerUpload'] ?? null,
                \Illuminate\Support\Facades\Auth::user()?->party?->taxId
            );
        }

        $payload = [
            'signed_medication_request_request' => $signedContent,
            'signed_content_encoding' => 'base64',
        ];

        $response = MedicationRequest::signMedicationRequest($requestRecord->uuid, $payload);

        $result = $response['data'] ?? $response;

        if (app()->bound(\App\Services\MedicalEvents\EHealthJobResolver::class)) {
            $finalResponse = app(\App\Services\MedicalEvents\EHealthJobResolver::class)->resolve($response);
            $requestRecord->update(['status' => 'active']);
            $result = $finalResponse['data'] ?? $finalResponse;
        }

        if (!isset($result['success_message'])) {
            $result['success_message'] = 'Рецепт успішно підписано КЕП та переведено у статус «Активний»!';
        }

        return $result;
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

        if (in_array(strtolower((string) $requestRecord->status), ['draft', 'new'], true)) {
            try {
                MedicationRequest::rejectUnsignedMedicationRequest((string) $requestRecord->uuid, []);
            } catch (\Exception $e) {
                Log::warning('eHealth reject draft returned exception: ' . $e->getMessage());
            }

            $requestRecord->update(['status' => 'rejected']);

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

            return $finalResponse['data'] ?? $finalResponse;
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

    /**
     * Build payload for KEP signing of Medication Request Request.
     *
     * @param  \App\Models\CarePlan  $carePlan
     * @param  \App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest  $requestRecord
     * @param  string  $informWith
     * @return array<string, mixed>
     */
    protected function buildSignPayload(\App\Models\CarePlan $carePlan, \App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest $requestRecord, string $informWith): array
    {
        if (!empty($requestRecord->ehealth_payload) && is_array($requestRecord->ehealth_payload)) {
            $signedContent = $requestRecord->ehealth_payload;
            if (isset($signedContent['data']) && is_array($signedContent['data'])) {
                $signedContent = $signedContent['data'];
            }
            Log::debug('ePrescription Sign Request Payload (from stored eHealth payload):', $signedContent);

            return $signedContent;
        }

        try {
            $personUuid = $carePlan->person->uuid ?? null;
            if ($personUuid) {
                $response = \App\Classes\eHealth\Facades\MedicationRequest::getRequestsBySearchParams((string) $personUuid, ['id' => $requestRecord->uuid]);
                $fetchedData = $response['data'][0] ?? ($response[0] ?? null);
                if (!empty($fetchedData) && is_array($fetchedData) && ($fetchedData['id'] ?? null) === $requestRecord->uuid) {
                    $requestRecord->update(['ehealth_payload' => $fetchedData]);
                    Log::debug('ePrescription Sign Request Payload (fetched from eHealth):', $fetchedData);

                    return $fetchedData;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not fetch MedicationRequestRequest from eHealth for signing fallback: ' . $e->getMessage());
        }

        $employee = \App\Models\Employee\Employee::find($requestRecord->employee_id);
        $division = \App\Models\Division::find($requestRecord->division_id);
        $encounter = \App\Models\MedicalEvents\Sql\Encounter::find($requestRecord->context_id);
        $activity = \App\Models\CarePlanActivity::find($requestRecord->based_on_id);

        $uuids = [
            'person_uuid' => $carePlan->person->uuid,
            'encounter_uuid' => $encounter ? $encounter->uuid : null,
            'employee_uuid' => $employee ? $employee->uuid : null,
            'division_uuid' => $division ? $division->uuid : null,
        ];

        $dosageInstructions = [];
        foreach ($requestRecord->dosageInstructions as $inst) {
            $doseAndRate = !empty($inst->dose_and_rate) && is_string($inst->dose_and_rate)
                ? json_decode($inst->dose_and_rate, true)
                : ($inst->dose_and_rate ?: []);

            $timing = !empty($inst->timing) && is_string($inst->timing)
                ? json_decode($inst->timing, true)
                : ($inst->timing ?: null);

            $text = !empty($inst->text) ? $inst->text : 'За призначенням лікаря';
            $patientInstruction = !empty($inst->patient_instruction) ? $inst->patient_instruction : $text;

            $dosageInstructions[] = [
                'sequence' => $inst->sequence ?? 1,
                'text' => $text,
                'patient_instruction' => $patientInstruction,
                'as_needed_boolean' => (bool) ($inst->as_needed_boolean ?? false),
                'route' => $inst->route ?? 'oral',
                'method' => $inst->method ?? null,
                'timing' => $timing,
                'dose_and_rate' => $doseAndRate,
                'max_dose_per_administration' => $inst->max_dose_per_administration !== null ? (float) $inst->max_dose_per_administration : null,
                'max_dose_per_period' => $inst->max_dose_per_period !== null ? (float) $inst->max_dose_per_period : null,
                'max_dose_per_lifetime' => $inst->max_dose_per_lifetime !== null ? (float) $inst->max_dose_per_lifetime : null,
            ];
        }

        $startedAt = !empty($requestRecord->started_at)
            ? \Carbon\Carbon::parse($requestRecord->started_at)->format('Y-m-d')
            : null;

        $endedAt = !empty($requestRecord->ended_at)
            ? \Carbon\Carbon::parse($requestRecord->ended_at)->format('Y-m-d')
            : null;

        $createdAt = !empty($requestRecord->created_at)
            ? \Carbon\Carbon::parse($requestRecord->created_at)->format('Y-m-d')
            : now()->format('Y-m-d');

        $data = [
            'created_at' => $createdAt,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'medication_id' => $requestRecord->medication_id,
            'medication_qty' => (float) $requestRecord->medication_qty,
            'medication_program_id' => $requestRecord->medication_program_id,
            'intent' => $requestRecord->intent ?? 'order',
            'category' => $requestRecord->category ?? 'community',
            'based_on_uuid' => $requestRecord->based_on_uuid ?? ($activity ? $activity->uuid : null),
            'container_dosage' => $requestRecord->container_dosage,
            'note' => $requestRecord->note,
            'inform_with' => $informWith !== '' ? $informWith : ($requestRecord->inform_with ?? ''),
            'dosage_instructions' => $dosageInstructions,
        ];

        $mapper = new \App\Services\MedicalEvents\Mappers\MedicationRequestMapper();
        $signedContent = $mapper->toCreateSignedContent($data, $uuids, $carePlan->uuid);

        Log::debug('ePrescription Sign Request Payload:', $signedContent);

        return $signedContent;
    }
}
