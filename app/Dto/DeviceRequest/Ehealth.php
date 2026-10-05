<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use App\Mapping\EHealth\Shared\EHealthReference;
use App\Mapping\Transforms\FhirCodeableConcept;
use App\Mapping\Transforms\FhirReference;
use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

class Ehealth
{
    public string $intent;
    public string $priority;

    #[Map(transform: [self::class, 'mapQuantity'])]
    public array $quantity;

    #[Map(source: 'uuids', transform: [self::class, 'mapEncounter'])]
    public ?array $encounter = null;

    #[Map(source: 'employeeUuid', transform: new FhirReference('employee'))]
    public array $requester;

    #[Map(source: 'mappedAt', transform: [self::class, 'mapAuthoredOn'])]
    public string $authored_on;

    #[Map(source: 'carePlanUuid', if: [self::class, 'hasActivity'], transform: [[self::class, 'mapBasedOn'], new MapCollection(targetClass: EHealthReference::class)])]
    public ?array $based_on = null;

    #[Map(source: 'device_id', if: [self::class, 'isDefinition'], transform: new FhirReference('device_definition'))]
    public ?array $code_reference = null;

    #[Map(source: 'device_id', if: [self::class, 'isClassification'], transform: new FhirCodeableConcept('device_definition_classification_type'))]
    public ?array $code = null;

    #[Map(source: 'mappedAt', transform: [self::class, 'mapOccurrence'])]
    public array $occurrence_period;

    #[Map(source: 'supporting_info', if: [self::class, 'hasReason'], transform: [[self::class, 'mapReferences'], new MapCollection(targetClass: EHealthReference::class)])]
    public ?array $reason = null;

    public static function mapQuantity(mixed $value, stdClass $source): array
    {
        return ['value' => (int) $value, 'system' => 'device_unit', 'code' => strtolower((string) ($source->quantity_code ?? 'piece'))];
    }

    public static function mapEncounter(array $uuids): ?array
    {
        if (!empty($uuids['encounter_uuid'])) {
            return self::reference('encounter', (string) $uuids['encounter_uuid']);
        }

        return !empty($uuids['episode_uuid']) ? self::reference('episode_of_care', (string) $uuids['episode_uuid']) : null;
    }

    public static function mapAuthoredOn(CarbonImmutable $now): string
    {
        // Preserve the signing clock-skew allowance; the caller owns the clock.
        return $now->utc()->subSeconds(30)->format('Y-m-d\TH:i:s.000\Z');
    }

    public static function hasActivity(?string $value, stdClass $source): bool
    {
        return !empty($value) && !empty($source->activityUuid);
    }

    public static function mapBasedOn(string $value, stdClass $source): array
    {
        return [(object) ['type' => 'care_plan', 'uuid' => $value], (object) ['type' => 'activity', 'uuid' => $source->activityUuid]];
    }

    public static function isDefinition(string $value, stdClass $source): bool
    {
        return $source->device_code_type === 'DEVICE_DEFINITION'
            || ($source->device_code_type !== 'CLASSIFICATION_TYPE' && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $value) === 1);
    }

    public static function isClassification(string $value, stdClass $source): bool
    {
        return !self::isDefinition($value, $source);
    }

    public static function hasReason(mixed $value): bool
    {
        return !empty($value);
    }

    public static function mapReferences(array $rows): array
    {
        $references = [];
        foreach ($rows as $row) {
            if (!empty($row['uuid']) && !empty($row['type'])) {
                $references[] = (object) ['type' => strtolower((string) $row['type']), 'uuid' => (string) $row['uuid']];
            }
        }

        return $references;
    }

    public static function mapOccurrence(CarbonImmutable $now, stdClass $source): array
    {
        $now = $now->utc();
        // Date-only inputs use the application's timezone, as the existing API contract does.
        $start = !empty($source->started_at) ? CarbonImmutable::parse($source->started_at)->utc() : $now;
        if ($start->lessThan($now)) {
            $start = $now;
        }

        $end = !empty($source->ended_at) ? CarbonImmutable::parse($source->ended_at)->utc() : $start->addMonths(3);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addDay();
        }

        return ['start' => $start->format('Y-m-d\TH:i:s.000\Z'), 'end' => $end->endOfDay()->format('Y-m-d\TH:i:s.000\Z')];
    }

    private static function reference(string $type, string $uuid): array
    {
        return (new FhirReference($type))($uuid, new stdClass(), null);
    }
}
