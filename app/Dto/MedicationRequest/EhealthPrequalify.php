<?php

declare(strict_types=1);

namespace App\Dto\MedicationRequest;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

final class EhealthPrequalify
{
    public array $medication_request_request;

    #[Map(transform: new MapCollection(targetClass: EhealthProgram::class))]
    public array $programs;
}
