<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Resource;

use App\Contracts\Dto\Model as ModelContract;
use App\Dto\FormCollection;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use Carbon\CarbonImmutable;
use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * Complete, normalized FHIR resource for persistence, after API response validation.
 * Kept separate from the editable Model DTO: omitted snapshot fields clear relations,
 * whereas editing a note must never replace the rest of the FHIR graph.
 */
#[Map(source: FormCollection::class)]
class Model implements ModelContract
{
    #[Map(source: '[id]')]
    public string $uuid;

    #[Map(source: '[status]')]
    public string $status;

    #[Map(source: '[requisition]')]
    public ?string $requestNumber = null;

    #[Map(source: '[note]')]
    public ?string $note = null;

    #[Map(source: '[patientInstruction]')]
    public ?string $patientInstruction = null;

    #[Map(source: '[priority]')]
    public ?string $priority = null;

    #[Map(source: '[intent]')]
    public ?string $intent = null;

    #[Map(source: '[occurrenceDateTime]')]
    public ?string $occurrenceDateTime = null;

    #[Map(source: '[programProcessingStatus]')]
    public ?string $programProcessingStatus = null;

    #[Map(source: '[authoredOn]')]
    public ?string $authoredOn = null;

    #[Map(source: '[explanatoryLetter]')]
    public ?string $explanatoryLetter = null;

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

    #[Map(source: '[subject]')]
    public ?array $subject = null;

    #[Map(source: '[patient]')]
    public ?array $patient = null;

    #[Map(source: '[performer]')]
    public ?array $performer = null;

    #[Map(source: '[locationReference]')]
    public ?array $locationReference = null;

    #[Map(source: '[category]')]
    public ?array $category = null;

    #[Map(source: '[performerType]')]
    public ?array $performerType = null;

    #[Map(source: '[statusReason]')]
    public ?array $statusReason = null;

    #[Map(source: '[quantity]')]
    public ?array $quantity = null;

    #[Map(source: '[remainingQuantity]')]
    public ?array $remainingQuantity = null;

    #[Map(source: '[occurrencePeriod]')]
    public ?array $occurrencePeriod = null;

    #[Map(source: '[informWith]')]
    public ?array $informWith = null;

    #[Map(source: '[basedOn]')]
    public ?array $basedOn = null;

    #[Map(source: '[supportingInfo]')]
    public ?array $supportingInfo = null;

    #[Map(source: '[reasonReference]')]
    public ?array $reasonReference = null;

    #[Map(source: '[permittedResources]')]
    public ?array $permittedResources = null;

    #[Map(source: '[specimens]')]
    public ?array $specimens = null;

    #[Map(source: '[focus]')]
    public ?array $focus = null;

    public function toModel(?ServiceRequestRequest $request = null): ServiceRequestRequest
    {
        $request ??= new ServiceRequestRequest();
        $request->fill([
            'uuid' => $this->uuid,
            'status' => $this->status,
            'clinicalStatus' => $this->status,
            'requestNumber' => $this->requestNumber,
            'serviceId' => data_get($this->code, 'identifier.value'),
            'quantity' => $this->quantity['value'] ?? null,
            'programId' => data_get($this->program, 'identifier.value'),
            'note' => $this->note,
            'patientInstruction' => $this->patientInstruction,
            'startedAt' => $this->sqlDate($this->occurrencePeriod['start'] ?? null),
            'endedAt' => $this->sqlDate($this->occurrencePeriod['end'] ?? null),
            'occurrenceDateTime' => $this->sqlDate($this->occurrenceDateTime),
            'programProcessingStatus' => $this->programProcessingStatus,
            'authoredOn' => $this->sqlDate($this->authoredOn),
            'explanatoryLetter' => $this->explanatoryLetter,
            'informWith' => $this->informWith['authMethodId'] ?? null,
            // Legacy read consumers still need UUID/type projections; ordered FHIR pivots are authoritative.
            'supportingInfo' => $this->legacyReferences($this->supportingInfo ?? []),
            'reasonReference' => $this->legacyReferences($this->reasonReference ?? []),
        ]);

        return $request;
    }

    protected function legacyReferences(array $references): array
    {
        return array_map(static fn (array $reference): array => [
            'uuid' => data_get($reference, 'identifier.value'),
            'type' => data_get($reference, 'identifier.type.coding.0.code'),
        ], $references);
    }

    protected function sqlDate(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s.u');
    }
}
