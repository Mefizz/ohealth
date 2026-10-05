<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class DeviceRequestPayloads
{
    // Signing depends on this order, including fields inherited by EhealthCreate.
    private const array FIELD_ORDER = ['intent', 'priority', 'quantity', 'encounter', 'requester', 'authored_on', 'based_on', 'code_reference', 'code', 'occurrence_period', 'reason', 'id', 'status', 'program'];

    private readonly Serializer $serializer;

    public function __construct(private readonly ObjectMapperInterface $mapper)
    {
        $this->serializer = new Serializer([new ObjectNormalizer()]);
    }

    public function prequalify(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        $request = $this->source($data, $uuids, $mappedAt, $carePlanUuid, $activityUuid);
        $source = (object) ['request' => $request, 'programs' => $request->program_id === null ? [] : [(object) ['type' => 'medical_program', 'uuid' => $request->program_id]]];
        $payload = $this->normalize($this->mapper->map($source, EhealthPrequalify::class));
        $payload['device_request'] = $this->orderBody($payload['device_request']);

        return $payload;
    }

    public function signedCreate(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null, ?string $activityUuid = null): array
    {
        return $this->orderBody($this->normalize($this->mapper->map($this->source($data, $uuids, $mappedAt, $carePlanUuid, $activityUuid), EhealthCreate::class)));
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

    private function normalize(object $payload): array
    {
        return $this->serializer->normalize($payload, context: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
    }

    private function orderBody(array $body): array
    {
        return array_replace(array_intersect_key(array_fill_keys(self::FIELD_ORDER, null), $body), $body);
    }
}
