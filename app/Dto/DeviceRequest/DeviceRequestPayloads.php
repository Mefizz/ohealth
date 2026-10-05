<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class DeviceRequestPayloads
{
    public function __construct(private readonly ObjectMapperInterface $mapper)
    {
    }

    public function prequalify(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        $request = $this->source($data, $uuids, $mappedAt, $carePlanUuid, $activityUuid);
        $source = (object) ['request' => $request, 'programs' => $request->program_id === null ? [] : [(object) ['type' => 'medical_program', 'uuid' => $request->program_id]]];

        return $this->mapper->map($source, EhealthPrequalify::class)->toArray();
    }

    public function signedCreate(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        return $this->mapper->map($this->source($data, $uuids, $mappedAt, $carePlanUuid, $activityUuid), EhealthCreate::class)->toArray();
    }

    private function source(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid, ?string $activityUuid): stdClass
    {
        // Native object adaptation of the existing validated arrays. Resolved context is explicit.
        return (object) array_replace($data, [
            'uuid' => $data['uuid'] ?? null,
            'intent' => $data['intent'] ?? 'order',
            'priority' => $data['priority'] ?? 'routine',
            'quantity' => $data['quantity'] ?? 1,
            'device_id' => (string) ($data['device_id'] ?? ''),
            'device_code_type' => $data['device_code_type'] ?? null,
            'supporting_info' => $data['supporting_info'] ?? null,
            'program_id' => !empty($data['program_id']) ? (string) $data['program_id'] : null,
            'employeeUuid' => (string) $uuids['employee_uuid'],
            'uuids' => $uuids,
            'mappedAt' => $mappedAt,
            'carePlanUuid' => $carePlanUuid,
            'activityUuid' => $activityUuid,
        ]);
    }

}
