<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\EHealth;
use App\Models\Employee\Employee;
use Illuminate\Support\Arr;

class MedicationDispenseLifecycleService
{
    public function __construct(private readonly EHealthJobResolver $jobResolver)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchByRequestNumber(string $requestNumber): array
    {
        $formatted = $this->formatRequestNumber($requestNumber);

        $response = EHealth::medicationRequest()->searchByPharmacy([
            'request_number' => $formatted,
        ]);

        $payload = $response->getData();
        $items = $payload['data'] ?? (isset($payload[0]) ? $payload : ($payload !== [] ? [$payload] : []));

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn ($item): bool => is_array($item)));
    }

    /**
     * Create a dispense draft and process it with a KEP signature.
     *
     * @param  array<string, mixed>  $medicationRequest
     * @param  array<string, mixed>  $form
     * @param  array{employee_uuid: string, division_uuid: string, signer_tax_id: ?string}  $employeeContext
     * @return array<string, mixed>
     */
    public function dispense(array $medicationRequest, array $form, array $employeeContext): array
    {
        $requestId = (string) ($medicationRequest['id'] ?? $medicationRequest['uuid'] ?? '');
        if ($requestId === '') {
            throw new \InvalidArgumentException('Немає ідентифікатора електронного рецепта.');
        }

        $medicationId = (string) (
            $medicationRequest['medication_id']
            ?? Arr::get($medicationRequest, 'medication.id')
            ?? Arr::get($medicationRequest, 'medication.identifier.value')
            ?? ''
        );

        $programId = (string) (
            $medicationRequest['medical_program_id']
            ?? Arr::get($medicationRequest, 'medical_program.id')
            ?? Arr::get($medicationRequest, 'medical_program.identifier.value')
            ?? ''
        );

        $createPayload = [
            'medication_dispense' => array_filter([
                'medication_request_id' => $requestId,
                'dispensed_at' => now()->toIso8601String(),
                'employee_id' => $employeeContext['employee_uuid'],
                'division_id' => $employeeContext['division_uuid'],
                'medication_id' => $medicationId !== '' ? $medicationId : null,
                'medication_qty' => (float) ($form['medication_qty'] ?? 0),
                'medical_program_id' => $programId !== '' ? $programId : null,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        $code = trim((string) ($form['code'] ?? ''));
        if ($code !== '') {
            $createPayload['code'] = $code;
        }

        $created = EHealth::medicationDispense()->create($createPayload)->getData();
        $entity = $created['data'] ?? $created;
        if (isset($entity[0]) && is_array($entity[0])) {
            $entity = $entity[0];
        }

        $dispenseId = (string) ($entity['id'] ?? $entity['uuid'] ?? '');
        if ($dispenseId === '') {
            throw new \RuntimeException('ЕСОЗ не повернула ідентифікатор відпуску ліків.');
        }

        $signPayload = is_array($entity) ? $entity : $createPayload['medication_dispense'];
        $signedContent = signatureService()->signData(
            $signPayload,
            $form['password'],
            $form['knedp'],
            $form['keyContainerUpload'] ?? null,
            $employeeContext['signer_tax_id']
        );

        $processed = EHealth::medicationDispense()->process($dispenseId, [
            'signed_medication_dispense' => $signedContent,
            'signed_content_encoding' => 'base64',
        ]);

        $final = $this->jobResolver->resolve($processed->getData());
        $this->jobResolver->assertSuccessful($final);

        return is_array($final) ? $final : ['status' => 'completed'];
    }

    /**
     * @return array{employee_uuid: string, division_uuid: string, signer_tax_id: ?string}
     */
    public function resolvePharmacyEmployeeContext(?Employee $employee): array
    {
        if ($employee === null || empty($employee->uuid)) {
            throw new \RuntimeException('Не знайдено співробітника аптеки для погашення рецепта.');
        }

        $divisionUuid = $employee->division?->uuid;
        if (empty($divisionUuid)) {
            throw new \RuntimeException('У співробітника аптеки не вказано місце надання послуг.');
        }

        return [
            'employee_uuid' => (string) $employee->uuid,
            'division_uuid' => (string) $divisionUuid,
            'signer_tax_id' => $employee->party?->taxId,
        ];
    }

    public function formatRequestNumber(string $requestNumber): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $requestNumber));

        return trim((string) preg_replace('/(.{4})/', '$1-', $clean), '-');
    }
}
