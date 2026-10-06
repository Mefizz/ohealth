<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

use App\Dto\EhealthMapping;
use App\Dto\Shared\EhealthReference;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

final class EhealthQualify
{
    use EhealthMapping;

    #[Map(source: 'programId', transform: [[self::class, 'programSources'], new MapCollection(targetClass: EhealthReference::class)])]
    public array $programs;

    public static function programSources(mixed $value): array
    {
        return [(object) ['type' => 'medical_program', 'uuid' => $value]];
    }
}
