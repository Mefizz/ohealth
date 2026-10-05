<?php

declare(strict_types=1);

namespace App\Dto\MedicationRequest;

use Carbon\CarbonImmutable;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class MedicationRequestPayloads
{
    public function __construct(private readonly ObjectMapperInterface $mapper)
    {
    }

    public function create(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null): array
    {
        return ['medication_request_request' => $this->signedContent($data, $uuids, $mappedAt, $carePlanUuid)];
    }

    public function prequalify(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null): array
    {
        $request = $this->signedContent($data, $uuids, $mappedAt, $carePlanUuid);
        unset($request['medical_program_id']);
        $source = (object) [
            'medication_request_request' => $request,
            'programs' => !empty($data['medication_program_id']) ? [(object) ['id' => $data['medication_program_id']]] : [],
        ];

        return $this->normalize($this->mapper->map($source, EhealthPrequalify::class));
    }

    /** This is only the fallback for drafts without an accepted raw eHealth document. */
    public function signedContent(array $data, array $uuids, CarbonImmutable $mappedAt, ?string $carePlanUuid = null): array
    {
        $references = $carePlanUuid && !empty($data['based_on_uuid']) ? [
            (object) ['type' => 'care_plan', 'uuid' => $carePlanUuid],
            (object) ['type' => 'activity', 'uuid' => $data['based_on_uuid']],
        ] : [];
        $instructions = [];
        foreach ($data['dosage_instructions'] ?? [] as $index => $instruction) {
            $instructions[] = (object) ['data' => $instruction, 'sequence' => $instruction['sequence'] ?? ($index + 1)];
        }
        $source = (object) ['data' => $data, 'uuids' => $uuids, 'mappedAt' => $mappedAt, 'based_on' => $references, 'instructions' => $instructions];

        return array_filter($this->normalize($this->mapper->map($source, Ehealth::class)), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function normalize(object $value): array
    {
        return (new Serializer([new ObjectNormalizer()]))->normalize($value, context: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
    }
}
