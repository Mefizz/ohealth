<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use Symfony\Component\ObjectMapper\Attribute\Map;

final class EhealthDraftPrequalify
{
    #[Map(source: 'patientId')]
    public string $person_id;

    #[Map(source: 'medicalProgram')]
    public string $program;

    public function toArray(): array
    {
        // This contract constructs one selected program, rather than mapping a source collection.
        return ['person_id' => $this->person_id, 'programs' => [['id' => $this->program]]];
    }
}
