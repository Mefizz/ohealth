<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\EHealthResponse;
use App\Core\Arr;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\Employee\Employee;
use App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest;
use App\Repositories\MedicalEvents\Repository;
use App\Services\MedicalEvents\Mappers\MedicationRequestMapper;
use Illuminate\Support\Facades\Auth;

class MedicationRequestLifecycleService
{
    public function __construct(
        private readonly EHealthJobResolver $jobResolver,
    ) {
    }

    /**
     * @return array{
     *     employee_id: int|null,
     *     division_id: int|null,
     *     employee_uuid: string|null,
     *     legal_entity_uuid: string|null
     * }
     */
    public function resolveEmployeeContext(CarePlan $carePlan, ?CarePlanActivity $activity = null, ?int $fallbackEmployeeId = null): array
    {
        $employee = null;

        $carePlan->loadMissing('encounter.performer');
        $performerUuid = $carePlan->encounter?->performer?->value;
        if (is_string($performerUuid) && $performerUuid !== '') {
            $employee = Employee::query()->where('uuid', $performerUuid)->first();
        }

        if (!$employee && $activity?->author_id) {
            $employee = Employee::find($activity->author_id);
        }

        if (!$employee && $fallbackEmployeeId) {
            $employee = Employee::find($fallbackEmployeeId);
        }

        if (!$employee) {
            $employee = Auth::user()?->activeDoctorEmployee();
        }

        return [
            'employee_id' => $employee?->id,
            'division_id' => $employee?->division_id ?? $carePlan->encounter?->division_id,
            'employee_uuid' => $employee?->uuid,
            'legal_entity_uuid' => $employee?->legalEntity?->uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @param  array<string, mixed>  $employeeContext
     */
    public function createDraft(CarePlan $carePlan, CarePlanActivity $activity, array $formData, array $employeeContext): string
    {
        $dbData = [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'draft',
            'intent' => 'order',
            'medication_id' => $formData['medication_id'],
            'medication_qty' => (float) $formData['medication_qty'],
            'medication_program_id' => $formData['program_id'] ?: null,
            'based_on_uuid' => $activity->uuid,
            'note' => $formData['signature_text'] ?? null,
            'dosage_instructions' => [
                [
                    'sequence' => 1,
                    'text' => $formData['signature_text'] ?? null,
                    'as_needed_boolean' => false,
                    'route' => '26643006',
                    'dose_and_rate' => [
                        [
                            'dose_quantity_value' => (float) $formData['max_dose_per_administration'],
                            'dose_quantity_unit' => $formData['medication_unit']
                        ]
                    ],
                    'max_dose_per_period' => $formData['max_dose_per_period'],
                    'max_dose_per_administration' => $formData['max_dose_per_administration'],
                ]
            ],
            'started_at' => convertToYmd($formData['started_at']),
            'ended_at' => convertToYmd($formData['ended_at']),
            'inform_with' => $formData['inform_with'],
        ];

        $uuids = [
            'person_uuid' => $carePlan->person->uuid,
            'encounter_uuid' => $carePlan->encounter?->uuid ?? null,
            'employee_uuid' => $employeeContext['employee_uuid'] ?? null,
            'legal_entity_uuid' => $employeeContext['legal_entity_uuid'] ?? null,
            'division_uuid' => $employeeContext['division_uuid'] ?? null,
        ];

        $mapper = new MedicationRequestMapper();
        $apiPayload = $mapper->toCreateRequestPayload($dbData, $uuids, $carePlan->uuid);

        $prequalifyResponse = EHealth::medicationRequest()->prequalify($apiPayload);
        $this->jobResolver->assertPrequalifyValid($prequalifyResponse->getData());

        $response = EHealth::medicationRequest()->createRequest($apiPayload);
        $responseData = $response->getData();

        $dbData['employee_id'] = $employeeContext['employee_id'] ?? null;
        $dbData['division_id'] = $employeeContext['division_id'] ?? null;
        $dbData['based_on_id'] = $activity->id;
        $dbData['context_id'] = $carePlan->encounter?->id ?? null;
        $dbData['request_number'] = $responseData['request_number'] ?? null;
        $dbData['status'] = $responseData['status'] ?? 'NEW';
        $dbData['uuid'] = $responseData['id'] ?? $dbData['uuid'];

        Repository::medicationRequest()->store($dbData, $carePlan->person_id);

        return $dbData['uuid'];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function sign(
        CarePlan $carePlan,
        MedicationRequestRequest $requestRecord,
        array $formData,
        string $informWithVal,
        float $remainingQty
    ): array {
        $uuids = [
            'person_uuid' => $carePlan->person->uuid,
            'encounter_uuid' => $carePlan->encounter?->uuid ?? null,
            'employee_uuid' => Auth::user()?->activeDoctorEmployee()?->uuid,
            'legal_entity_uuid' => Auth::user()?->activeDoctorEmployee()?->legalEntity?->uuid,
        ];

        $dbData = $requestRecord->toArray();
        $dbData['dosage_instructions'] = $requestRecord->dosageInstructions()->get()->toArray();
        $dbData['dosage_instructions'] = array_map(function ($inst) {
            if (is_string($inst['timing'])) {
                $inst['timing'] = json_decode($inst['timing'], true);
            }
            if (is_string($inst['dose_and_rate'])) {
                $inst['dose_and_rate'] = json_decode($inst['dose_and_rate'], true);
            }
            return $inst;
        }, $dbData['dosage_instructions']);

        $activity = CarePlanActivity::find($requestRecord->based_on_id);
        $dbData['based_on_uuid'] = $activity?->uuid;

        $mapper = new MedicationRequestMapper();
        $fhirPayload = $mapper->toFhir($dbData, $uuids);

        $informWithId = explode('|', $informWithVal)[0] ?? '';
        $fhirPayload['inform_with'] = [
            'identifier' => [
                'value' => $informWithId
            ]
        ];

        $signedContent = signatureService()->signData(
            Arr::toSnakeCase($fhirPayload),
            $formData['password'],
            $formData['knedp'],
            $formData['keyContainerUpload'],
            Auth::user()->party->taxId
        );

        $eHealthResponse = EHealth::medicationRequest()->signRequest(
            $requestRecord->uuid,
            [
                'signed_data' => $signedContent,
                'signed_data_encoding' => 'base64',
            ]
        );

        $responseData = $eHealthResponse->getData();
        $finalResponse = $this->jobResolver->resolve($responseData);

        if (in_array(strtolower((string) ($finalResponse['status'] ?? '')), ['failed', 'error'], true)) {
            throw new EHealthValidationException($finalResponse);
        }

        $entity = isset($finalResponse['result'][0]) ? ($finalResponse['result'][0] ?? $finalResponse['result']) : $finalResponse;
        $requestNumber = $entity['request_number'] ?? $requestRecord->request_number;
        $finalStatus = $entity['status'] ?? 'active';

        $requestRecord->update([
            'status' => $finalStatus,
            'request_number' => $requestNumber,
        ]);

        if ($activity && $activity->status === 'scheduled') {
            $activity->update(['status' => 'in-progress']);
        }

        $warningMessage = null;
        $newRemainingQty = $remainingQty - $requestRecord->medication_qty;
        if ($newRemainingQty < $requestRecord->medication_qty) {
            $unit = $dbData['dosage_instructions'][0]['dose_and_rate'][0]['dose_quantity_unit'] ?? '';
            $warningMessage = "Увага! Для пацієнта в плані лікування залишалось лікарського засобу в кількості {$remainingQty} {$unit}. Повідомте пацієнту, що для подальшого отримання ліків необхідно звернутись до лікаря для коригування плану.";
        }

        $authMethodName = explode('|', $informWithVal)[1] ?? 'OTP';
        $phoneNumber = explode('|', $informWithVal)[2] ?? '';

        if (in_array(strtolower((string) $finalStatus), ['pending', 'processing'], true)) {
            $successMsg = 'Запит на е-рецепт прийнято в обробку ЕСОЗ. Фінальний статус та номер рецепта з’являться після завершення асинхронної задачі.';
        } elseif (strtoupper($authMethodName) === 'OTP' || strtoupper($authMethodName) === 'THIRD_PERSON') {
            $successMsg = "Електронний рецепт № {$requestNumber} створено в електронній системі охорони здоров’я. Номер рецепта та код погашення надіслано в СМС-повідомленні на номер {$phoneNumber}. Не забудьте попередити про це пацієнта! При необхідності роздрукуйте інформаційну пам’ятку пацієнту.";
        } else {
            $successMsg = "Електронний рецепт № {$requestNumber} створено в електронній системі охорони здоров’я. Код погашення зазначено в друкованій інформаційній пам’ятці. Не забудьте повідомити дані пацієнту та обов`язково роздрукувати інформаційну пам’ятку з кодом погашення!";
        }

        return [
            'success_message' => $successMsg,
            'warning_message' => $warningMessage,
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function reject(
        CarePlan $carePlan,
        MedicationRequestRequest $requestRecord,
        array $formData = [],
        ?string $statusReason = null
    ): void {
        if (strtolower((string) $requestRecord->status) === 'new') {
            EHealth::medicationRequest()->rejectRequest($requestRecord->uuid);
        } else {
            $payload = [
                'reject_reason_code' => $statusReason ?: 'patient_left_the_program'
            ];

            $signedContent = signatureService()->signData(
                Arr::toSnakeCase($payload),
                $formData['password'],
                $formData['knedp'],
                $formData['keyContainerUpload'],
                Auth::user()->party->taxId
            );

            $response = EHealth::medicationRequest()->reject($carePlan->person->uuid, $requestRecord->uuid, [
                'signed_data' => $signedContent,
                'signed_data_encoding' => 'base64',
                'reject_reason_code' => $payload['reject_reason_code'],
            ]);

            if (!$response->successful()) {
                throw new \Exception(json_encode($response->getData()));
            }
        }

        $requestRecord->update(['status' => 'rejected']);
    }

    public function fetchPrintoutFromEhealth(string $personUuid, string $prescriptionId): ?string
    {
        try {
            $response = EHealth::person()->getMedicationRequestPrintoutForm($personUuid, $prescriptionId);
            $printout = $response->getData()['printout_form'] ?? null;

            return !empty($printout) ? $printout : null;
        } catch (EHealthValidationException) {
            return null;
        }
    }

    public function buildFallbackPrintoutHtml(CarePlan $carePlan, string $prescriptionId, ?string $signatureText = null): string
    {
        $requestRecord = Repository::medicationRequest()->findByUuid($prescriptionId);
        $medicationName = $requestRecord?->medication_id ?? $prescriptionId;
        $signatureText ??= $requestRecord?->note ?? '';

        return "
            <div style='font-family: sans-serif; padding: 40px; max-width: 600px; margin: 0 auto; border: 1px solid #ccc; border-radius: 8px;'>
                <h2 style='text-align: center; color: #1e3a8a;'>ІНФОРМАЦІЙНА ПАМ’ЯТКА ПАЦІЄНТА</h2>
                <p style='text-align: center; font-size: 14px; color: #555;'>Електронний рецепт № " . e($requestRecord?->request_number ?? $prescriptionId) . "</p>
                <hr style='border-top: 1px solid #eee; margin: 20px 0;'/>
                <table style='width: 100%; font-size: 14px; border-collapse: collapse;'>
                    <tr><td style='padding: 8px 0; font-weight: bold;'>Пацієнт:</td><td style='padding: 8px 0;'>" . e($carePlan->person->full_name) . "</td></tr>
                    <tr><td style='padding: 8px 0; font-weight: bold;'>Лікарський засіб (МНН):</td><td style='padding: 8px 0;'>" . e($medicationName) . "</td></tr>
                    <tr><td style='padding: 8px 0; font-weight: bold;'>Код погашення:</td><td style='padding: 8px 0; font-size: 18px; font-weight: bold; color: #10b981;'>[Код в СМС / Доступний в аптеці]</td></tr>
                    <tr><td style='padding: 8px 0; font-weight: bold;'>Сигнатура:</td><td style='padding: 8px 0;'>" . e($signatureText) . "</td></tr>
                </table>
                <div style='margin-top: 40px; text-align: center; font-size: 12px; color: #888;'>
                    Виписано в МІС. Дякуємо, що користуєтесь нашими послугами!
                </div>
            </div>
        ";
    }

    public function resendSms(string $personUuid, string $prescriptionId): EHealthResponse
    {
        return EHealth::medicationRequest()->resendOtp($personUuid, $prescriptionId);
    }
}
