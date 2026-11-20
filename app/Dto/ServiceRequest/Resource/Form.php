<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Resource;

use App\Dto\FormCollection;
use Illuminate\Support\Str;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Editable fields projected from a complete FHIR referral for encounter and care-plan forms. */
#[Map(source: FormCollection::class)]
class Form
{
    #[Map(source: '[id?]')]
    public ?string $uuid = null;

    #[Map(source: '[status?]')]
    public ?string $status = null;

    #[Map(source: '[requisition?]')]
    public ?string $requestNumber = null;

    #[Map(source: '[occurrenceDateTime?]')]
    public ?string $occurrenceDateTime = null;

    #[Map(source: '[occurrencePeriod?][start?]')]
    public ?string $startedAt = null;

    #[Map(source: '[occurrencePeriod?][end?]')]
    public ?string $endedAt = null;

    #[Map(source: '[code?][identifier?][value?]')]
    public ?string $serviceId = null;

    #[Map(source: '[code?][identifier?][type?][coding?][0?][code?]')]
    public ?string $serviceType = null;

    #[Map(source: '[quantity?][value?]')]
    public float|int|string|null $quantity = null;

    #[Map(source: '[quantity?][system?]')]
    public ?string $quantitySystem = null;

    #[Map(source: '[quantity?][code?]')]
    public ?string $quantityCode = null;

    #[Map(source: '[quantity?][unit?]')]
    public ?string $quantityUnit = null;

    #[Map(source: '[quantity?][comparator?]')]
    public ?string $quantityComparator = null;

    #[Map(source: '[program?][identifier?][value?]')]
    public ?string $programId = null;

    #[Map(source: '[intent?]')]
    public ?string $intent = null;

    #[Map(source: '[category?][coding?][0?][code?]')]
    public ?string $category = null;

    #[Map(source: '[context?][identifier?][value?]')]
    public ?string $contextUuid = null;

    #[Map(source: '[context?][identifier?][type?][coding?][0?][code?]')]
    public ?string $contextType = null;

    #[Map(source: '[priority?]')]
    public ?string $priority = null;

    #[Map(source: '[note?]')]
    public ?string $note = null;

    #[Map(source: '[patientInstruction?]')]
    public ?string $patientInstruction = null;

    #[Map(source: '[informWith?]')]
    public ?array $informWith = null;

    #[Map(source: '[performer?][identifier?][value?]')]
    public ?string $performer = null;

    #[Map(source: '[locationReference?][identifier?][value?]')]
    public ?string $locationReference = null;

    #[Map(source: '[performerType?][coding?][0?][code?]')]
    public ?string $performerType = null;

    #[Map(source: '[basedOn?]')]
    public ?array $basedOn = null;

    #[Map(source: '[supportingInfo?]')]
    public ?array $supportingInfo = null;

    #[Map(source: '[reasonReference?]')]
    public ?array $reasonReference = null;

    public function toArray(): array
    {
        $fields = [];
        foreach (get_object_vars($this) as $key => $value) {
            $fields[Str::snake($key)] = $value;
        }
        unset($fields['based_on']);
        $activity = collect($this->basedOn)->first(static fn (array $ref): bool => in_array(
            data_get($ref, 'identifier.type.coding.0.code'), ['activity', 'care_plan_activity'], true
        ));
        $fields['based_on_uuid'] = data_get($activity, 'identifier.value');

        foreach (['supporting_info', 'reason_reference'] as $key) {
            $fields[$key] = array_map(static function (array $reference): array {
                $uuid = data_get($reference, 'identifier.value');
                $type = data_get($reference, 'identifier.type.coding.0.code');

                // Display-only references cannot be represented by a local UUID/type pair.
                return $uuid !== null && $type !== null ? ['uuid' => $uuid, 'type' => $type] : $reference;
            }, $fields[$key] ?? []);
        }

        return $fields;
    }
}
