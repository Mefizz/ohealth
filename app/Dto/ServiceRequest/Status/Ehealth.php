<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Status;

use App\Livewire\Referral\Forms\ServiceRequestForm;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Cancel/recall signs the complete native snapshot, retaining every FHIR extension and reference. */
#[Map(source: ServiceRequestForm::class)]
class Ehealth
{
    #[Map(source: 'resource')]
    public array $resource = [];

    #[Map(source: 'statusReason')]
    public array $statusReason = [];

    #[Map(source: 'explanatoryLetter')]
    public string $explanatoryLetter = '';

    public function toArray(): array
    {
        return array_replace($this->resource, [
            'status_reason' => $this->statusReason,
            'explanatory_letter' => $this->explanatoryLetter,
        ]);
    }
}
