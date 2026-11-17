<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

use App\Core\Arr;
use App\Dto\FormCollection;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Creation content only: response/history fields must never enter the signed create payload. */
#[Map(source: FormCollection::class)]
class Ehealth
{
    #[Map(if: false)]
    public string $status = 'active';

    #[Map(source: '[id]')]
    public ?string $id = null;

    #[Map(source: '[intent]')]
    public ?string $intent = null;

    #[Map(source: '[priority]')]
    public ?string $priority = null;

    #[Map(source: '[basedOn]')]
    public ?array $basedOn = null;

    #[Map(source: '[category]')]
    public ?array $category = null;

    #[Map(source: '[code]')]
    public ?array $code = null;

    #[Map(source: '[program]')]
    public ?array $program = null;

    #[Map(source: '[context]')]
    public ?array $context = null;

    #[Map(source: '[requesterEmployee]')]
    public ?array $requesterEmployee = null;

    #[Map(source: '[requesterLegalEntity]')]
    public ?array $requesterLegalEntity = null;

    #[Map(source: '[reasonReference]')]
    public ?array $reasonReference = null;

    #[Map(source: '[supportingInfo]')]
    public ?array $supportingInfo = null;

    #[Map(source: '[note]')]
    public ?string $note = null;

    #[Map(source: '[patientInstruction]')]
    public ?string $patientInstruction = null;

    #[Map(source: '[permittedResources]')]
    public ?array $permittedResources = null;

    #[Map(source: '[performer]')]
    public ?array $performer = null;

    #[Map(source: '[locationReference]')]
    public ?array $locationReference = null;

    #[Map(source: '[performerType]')]
    public ?array $performerType = null;

    #[Map(source: '[quantity]')]
    public ?array $quantity = null;

    #[Map(source: '[specimens]')]
    public ?array $specimens = null;

    #[Map(source: '[focus]')]
    public ?array $focus = null;

    #[Map(source: '[informWith]')]
    public ?array $informWith = null;

    #[Map(source: '[occurrencePeriod]')]
    public ?array $occurrencePeriod = null;

    #[Map(source: '[occurrenceDateTime]')]
    public ?string $occurrenceDateTime = null;

    public function toArray(): array
    {
        $data = get_object_vars($this);
        $data['intent'] ??= 'order';
        $data['priority'] ??= 'routine';
        if (data_get($this->category, 'coding.0.code') !== 'transfer_of_care') {
            unset($data['performer'], $data['locationReference'], $data['performerType']);
        }

        // Keep nested FHIR values and metadata, including numeric zero; generic empty-key removal loses them.
        return Arr::toSnakeCase(array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []));
    }
}
