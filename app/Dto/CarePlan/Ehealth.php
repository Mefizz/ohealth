<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Core\Arr;
use App\Enums\CarePlanStatus;
use ArrayObject;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Fields permitted in the signed Create Care Plan content. */
#[Map(source: ArrayObject::class)]
class Ehealth
{
    #[Map(source: '[id]')]
    public string $id;

    #[Map(source: '[basedOn?]')]
    public ?array $basedOn = null;

    #[Map(source: '[partOf?]')]
    public ?array $partOf = null;

    #[Map(source: '[category]')]
    public array $category;

    #[Map(source: '[title]')]
    public string $title;

    #[Map(source: '[description?]')]
    public ?string $description = null;

    #[Map(source: '[period]')]
    public array $period;

    #[Map(source: '[supportingInfo?]')]
    public ?array $supportingInfo = null;

    #[Map(source: '[note?]')]
    public ?string $note = null;

    #[Map(source: '[encounter]')]
    public array $encounter;

    #[Map(source: '[addresses]')]
    public array $addresses;

    #[Map(source: '[author]')]
    public array $author;

    #[Map(source: '[contributor?]')]
    public ?array $contributor = null;

    #[Map(source: '[termsOfService]')]
    public array $termsOfService;

    #[Map(source: '[informWith?]')]
    public ?array $informWith = null;

    #[Map(if: false)]
    public string $intent = 'order';

    #[Map(if: false)]
    public string $status = CarePlanStatus::PENDING->value;

    /** Return the native, validated array passed to the signature provider. */
    public function signingPayload(): array
    {
        $payload = $this->omitEmptyFields(Arr::toSnakeCase(get_object_vars($this)));
        Validator::make($payload, [
            'id' => 'required|uuid',
            'title' => 'required|string',
            'intent' => 'required|in:order',
            'status' => 'required|in:new',
            'category.coding' => 'required|array|min:1',
            'period.start' => 'required|date',
            'period.end' => 'sometimes|nullable|date|after_or_equal:period.start',
            'encounter.identifier.value' => 'required|uuid',
            'author.identifier.value' => 'required|uuid',
            'addresses' => 'required|array|min:1',
            'terms_of_service.coding' => 'required|array|min:1',
            'based_on' => 'sometimes|nullable|array',
            'part_of' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string',
            'note' => 'sometimes|nullable|string',
            'supporting_info' => 'sometimes|nullable|array',
            'supporting_info.*.identifier.value' => 'required|uuid',
            'supporting_info.*.identifier.type.coding' => 'required|array|min:1',
            'supporting_info.*.identifier.type.coding.*.code' => 'required|in:episode_of_care,procedure,diagnostic_report',
            'contributor' => 'sometimes|nullable|array',
            'contributor.*.identifier.value' => 'required|uuid',
            'inform_with' => 'sometimes|nullable|array',
            'inform_with.auth_method_id' => 'sometimes|nullable|uuid',
        ])->validate();

        return $payload;
    }

    /** Optional empty fields are omitted and JSON lists keep consecutive indexes. */
    protected function omitEmptyFields(array $data): array
    {
        $data = array_map(fn (mixed $value): mixed => is_array($value) ? $this->omitEmptyFields($value) : $value, $data);
        $filtered = array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return array_is_list($data) ? array_values($filtered) : $filtered;
    }
}
