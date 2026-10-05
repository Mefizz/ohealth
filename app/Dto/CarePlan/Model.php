<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Livewire\CarePlan\Forms\CarePlanForm;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Local editable fields only; ownership, author and encounter context are resolved by the caller. */
#[Map(source: CarePlanForm::class)]
final class Model
{
    public string $category;

    #[Map(transform: [self::class, 'optionalString'])]
    public ?string $context = null;

    public string $title;

    #[Map(source: 'termsOfService', transform: [self::class, 'optionalString'])]
    public ?string $terms_of_service = null;

    #[Map(source: 'periodStart', transform: 'convertToYmd')]
    public string $period_start;

    #[Map(source: 'periodEnd', transform: [self::class, 'optionalDate'])]
    public ?string $period_end = null;

    #[Map(source: 'episodes', transform: [self::class, 'mapSupportingInfo'])]
    public array $supporting_info;

    #[Map(transform: [self::class, 'optionalString'])]
    public ?string $description = null;

    #[Map(transform: [self::class, 'optionalString'])]
    public ?string $note = null;

    #[Map(source: 'informWith', transform: [self::class, 'optionalString'])]
    public ?string $inform_with = null;

    public static function optionalString(string $value): ?string
    {
        return $value ?: null;
    }

    public static function optionalDate(string $value): ?string
    {
        return $value ? convertToYmd($value) : null;
    }

    public static function mapSupportingInfo(array $episodes, CarePlanForm $source): array
    {
        // These are local display snapshots; retain their keys and fields rather than emitting FHIR references.
        return ['episodes' => $episodes, 'medical_records' => $source->medicalRecords];
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
