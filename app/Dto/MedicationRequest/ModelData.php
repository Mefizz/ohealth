<?php

declare(strict_types=1);

namespace App\Dto\MedicationRequest;

use App\Mapping\Transforms\FallbackValue;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Partial list/detail metadata. Author, clinical links, dosage and raw signing data stay local. */
final class ModelData
{
    #[Map(source: 'status?')]
    public ?string $status = null;

    #[Map(source: 'request_number?', transform: new FallbackValue('requisition'))]
    public ?string $request_number = null;

    #[Map(source: 'started_at?')]
    public ?string $started_at = null;

    #[Map(source: 'ended_at?')]
    public ?string $ended_at = null;

    #[Map(source: 'medication_id?', transform: new FallbackValue('medication_info.id'))]
    public ?string $medication_id = null;

    #[Map(source: 'medication_qty?')]
    public int|float|string|null $medication_qty = null;

    #[Map(source: 'medical_program_id?', transform: new FallbackValue('medical_program.id'))]
    public ?string $medication_program_id = null;

    public function toSyncPatch(): array
    {
        // The existing medication cache contract retains zero and explicit empty strings.
        return array_filter(get_object_vars($this), static fn (mixed $value): bool => $value !== null);
    }
}
