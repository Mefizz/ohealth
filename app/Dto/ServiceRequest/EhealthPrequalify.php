<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

use App\Dto\FormCollection;
use Illuminate\Support\Arr;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Project the clinical content used for signing into the prequalification envelope. */
#[Map(source: FormCollection::class)]
class EhealthPrequalify
{
    #[Map(source: 'all')]
    public array $serviceRequest;

    #[Map(source: '[program?]')]
    public ?array $program = null;

    public function toArray(): array
    {
        return [
            'service_request' => Arr::except($this->serviceRequest, ['id', 'program']),
            'programs' => $this->program === null ? [] : [$this->program],
        ];
    }
}
