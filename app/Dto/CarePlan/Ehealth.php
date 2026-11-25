<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Core\Arr;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Services\MedicalEvents\Fhir;
use App\Services\MedicalEvents\FhirResource;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: CarePlanForm::class)]
class Ehealth
{
    public string $title = '';
    public string $category = '';
    public string $termsOfService = '';
    public string $encounter = '';
    public string $periodStart = '';
    public string $periodStartTime = '';
    public string $periodEnd = '';
    public string $periodEndTime = '';
    public string $description = '';
    public string $note = '';
    public string $context = '';
    public string $informWith = '';
    public array $coAuthors = [];
    public array $episodes = [];
    public array $medicalRecords = [];

    protected array $resource;

    /** Overlay validated, editable fields on the stored local draft. */
    public function withFormChanges(array $stored, string $authorUuid, array $addresses): self
    {
        Validator::make([
            'medicalRecords' => $this->medicalRecords,
            'coAuthors' => $this->coAuthors,
            'episodes' => $this->episodes,
        ], [
            'medicalRecords.*' => 'array',
            'coAuthors.*' => 'nullable|uuid',
            'episodes.*' => 'array',
            'episodes.*.uuid' => 'required|uuid',
        ])->validate();

        $references = collect($this->medicalRecords)->map(function (array $reference): array {
            if (isset($reference['identifier'])) {
                return $reference;
            }

            Validator::make($reference, [
                'uuid' => 'required|uuid',
                'type' => 'required|in:procedure,diagnostic_report',
            ])->validate();

            return FhirResource::make()->coding('eHealth/resources', $reference['type'])->toIdentifier($reference['uuid']);
        })->all();

        return $this->fromResource(Fhir::carePlan()->toFhir([
            'title' => $this->title,
            'category' => $this->category,
            'termsOfService' => $this->termsOfService,
            'periodStart' => $this->periodStart,
            'periodStartTime' => $this->periodStartTime,
            'periodEnd' => $this->periodEnd,
            'periodEndTime' => $this->periodEndTime,
            'description' => $this->description,
            'note' => $this->note,
            'context' => $this->context,
            'encounter' => $this->encounter,
            'informWith' => $this->informWith,
            'author' => $authorUuid,
            'addresses' => $addresses,
            'coAuthors' => array_values(array_filter($this->coAuthors)),
            'episodes' => $this->episodes,
            'medicalRecords' => $references,
        ], ['resource' => $stored, 'timezone' => config('app.timezone'), 'dateFormat' => config('app.date_format')]));
    }

    public function fromResource(array $resource): self
    {
        Validator::make($resource, [
            'id' => 'required|uuid',
            'title' => 'required|string',
            'category.coding' => 'required|array|min:1',
            'period.start' => 'required|date',
            'period.end' => 'sometimes|nullable|date|after_or_equal:period.start',
            'encounter.identifier.value' => 'required|uuid',
            'author.identifier.value' => 'required|uuid',
            'addresses' => 'required|array|min:1',
            'termsOfService.coding' => 'required|array|min:1',
            'supportingInfo.*.identifier.value' => 'required|uuid',
            'supportingInfo.*.identifier.type.coding' => 'required|array|min:1',
            'supportingInfo.*.identifier.type.coding.*.code' => 'required|in:episode_of_care,procedure,diagnostic_report',
            'contributor.*.identifier.value' => 'required|uuid',
            'informWith.authMethodId' => 'sometimes|nullable|uuid',
        ])->validate();
        $this->resource = $resource;

        return $this;
    }

    public function localResource(): array
    {
        return $this->resource;
    }

    /** The native snake_case content signed before POST; omit empty optional fields. */
    public function toArray(): array
    {
        $content = array_intersect_key($this->resource, array_flip([
            'id', 'basedOn', 'partOf', 'category', 'title', 'description', 'period', 'supportingInfo',
            'note', 'encounter', 'addresses', 'author', 'contributor', 'termsOfService', 'informWith',
        ]));
        $content['intent'] = 'order';
        $content['status'] = 'new';

        return $this->omitEmpty(Arr::toSnakeCase($content));
    }

    protected function omitEmpty(array $data): array
    {
        $data = array_map(fn (mixed $value): mixed => is_array($value) ? $this->omitEmpty($value) : $value, $data);
        $filtered = array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return array_is_list($data) ? array_values($filtered) : $filtered;
    }
}
