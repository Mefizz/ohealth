<?php

declare(strict_types=1);

namespace App\Livewire\Referral\Forms;

use Livewire\Form;

/** Validation state for the signed clinical content, independent of KEP credentials. */
class ServiceRequestForm extends Form
{
    public array $resource = [];

    public array $statusReason = [];

    public string $explanatoryLetter = '';

    public function setCreate(array $resource): void
    {
        $this->reset();
        $this->resource = $resource;
        $this->validate([
            'resource.id' => ['required', 'uuid'],
            'resource.code.identifier.value' => ['required', 'string'],
            'resource.requesterEmployee.identifier.value' => ['required', 'uuid'],
            'resource.requesterLegalEntity.identifier.value' => ['required', 'uuid'],
            'resource.occurrencePeriod' => ['required_without:resource.occurrenceDateTime', 'missing_with:resource.occurrenceDateTime', 'array'],
            'resource.occurrencePeriod.start' => ['required_with:resource.occurrencePeriod', 'date', 'after_or_equal:now'],
            'resource.occurrencePeriod.end' => ['sometimes', 'date', 'after_or_equal:resource.occurrencePeriod.start'],
            'resource.occurrenceDateTime' => ['required_without:resource.occurrencePeriod', 'date', 'after_or_equal:now'],
        ]);
    }

    public function setStatus(array $snapshot, array|string $reason, string $letter, string $system): void
    {
        $this->reset();
        $this->resource = $snapshot;
        $this->statusReason = is_array($reason) ? $reason : ['coding' => [['system' => $system, 'code' => $reason]]];
        $this->explanatoryLetter = trim($letter);
        $this->validate([
            'resource.id' => ['required', 'uuid'],
            'statusReason.coding' => ['required', 'array', 'min:1', 'list'],
            'statusReason.coding.*.system' => ['required', 'string'],
            'statusReason.coding.*.code' => ['required', 'string'],
            'explanatoryLetter' => ['required', 'string'],
        ]);
    }
}
