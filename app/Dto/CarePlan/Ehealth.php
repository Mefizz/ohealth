<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Core\Arr;
use App\Dto\Input\FormCollection;
use App\Models\CarePlanRequest;
use App\Services\MedicalEvents\FhirResource;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;

/** The clinical content signed for Create Care Plan, projected from the validated draft resource. */
#[Map(source: FormCollection::class)]
#[Map(source: CarePlanRequest::class)]
class Ehealth
{
    #[Map(source: '[id]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[uuid]', if: new SourceClass(CarePlanRequest::class))]
    public string $id;

    #[Map(source: '[category]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[category]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'categoryConcept'])]
    public array $category;

    #[Map(source: '[title]')]
    public string $title;

    #[Map(source: '[description?]')]
    public ?string $description = null;

    #[Map(source: '[period]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[periodStart]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'periodOfRevision'])]
    public array $period;

    #[Map(source: '[supportingInfo?]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[episodes]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'supportingReferences'])]
    public ?array $supportingInfo = null;

    #[Map(source: '[note?]')]
    public ?string $note = null;

    #[Map(source: '[encounter]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[encounter]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'encounterReference'])]
    public array $encounter;

    #[Map(source: '[addresses]')]
    public array $addresses;

    #[Map(source: '[author]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[authorUuid?]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'authorReference'])]
    public array $author;

    #[Map(source: '[contributor?]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[coAuthors]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'contributors'])]
    public ?array $contributor = null;

    #[Map(source: '[termsOfService]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[termsOfService]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'termsConcept'])]
    public array $termsOfService;

    #[Map(source: '[informWith?]', if: new SourceClass(FormCollection::class))]
    #[Map(source: '[informWith]', if: new SourceClass(CarePlanRequest::class), transform: [new self(), 'authMethod'])]
    public ?array $informWith = null;

    #[Map(if: false)]
    public string $intent = 'order';

    #[Map(if: false)]
    public string $status = 'new';

    public function categoryConcept(string $code): array
    {
        return FhirResource::make()->coding('eHealth/care_plan_categories', $code)->toCodeableConcept();
    }

    public function termsConcept(string $code): array
    {
        return FhirResource::make()->coding('PROVIDING_CONDITION', $code)->toCodeableConcept();
    }

    public function authorReference(?string $uuid): array
    {
        return $uuid ? FhirResource::make()->coding('eHealth/resources', 'employee')->toIdentifier($uuid) : [];
    }

    public function encounterReference(string $uuid): array
    {
        return FhirResource::make()->coding('eHealth/resources', 'encounter')->toIdentifier($uuid);
    }

    public function contributors(array $authors): array
    {
        return array_map(fn ($author): array => is_array($author) ? $author : $this->authorReference($author), $authors);
    }

    public function authMethod(?string $uuid): ?array
    {
        return filled($uuid) ? ['authMethodId' => $uuid] : null;
    }

    public function supportingReferences(array $episodes, CarePlanRequest $request): array
    {
        $references = [];
        foreach ($episodes as $episode) {
            $uuid = is_array($episode) ? ($episode['uuid'] ?? ($episode['id'] ?? null)) : $episode;
            if (isset($episode['identifier'])) {
                $references[] = $episode;
            } elseif (is_string($uuid) && $uuid !== '') {
                $references[] = FhirResource::make()->coding('eHealth/resources', 'episode_of_care')->toIdentifier($uuid);
            } else {
                throw ValidationException::withMessages(['form.episodes' => __('validation.uuid', ['attribute' => __('care-plan.episode')])]);
            }
        }
        foreach ($request->medicalRecords as $record) {
            if (isset($record['identifier'])) {
                $references[] = $record;
            } elseif (isset($record['uuid']) && in_array($record['type'] ?? null, ['procedure', 'diagnostic_report'], true)) {
                $references[] = FhirResource::make()->coding('eHealth/resources', $record['type'])->toIdentifier($record['uuid']);
            } else {
                throw ValidationException::withMessages(['form.medicalRecords' => __('validation.in', ['attribute' => __('care-plan.supporting_info')])]);
            }
        }

        return $references;
    }

    public function periodOfRevision(string $start, CarePlanRequest $request): array
    {
        $format = '!'.config('app.date_format');
        $timezone = config('app.timezone');
        $period = ['start' => CarbonImmutable::createFromFormat($format, $start, $timezone)
            ->setTimeFromTimeString($request->periodStartTime ?: '00:00:00')->utc()->format('Y-m-d\TH:i:s\Z')];
        if ($request->periodEnd !== null) {
            $period['end'] = CarbonImmutable::createFromFormat($format, $request->periodEnd, $timezone)
                ->setTimeFromTimeString($request->periodEndTime ?: '23:59:59')->utc()->format('Y-m-d\TH:i:s\Z');
        }

        return $period;
    }

    public function toArray(): array
    {
        return $this->withoutEmptyValues(Arr::toSnakeCase(get_object_vars($this)));
    }

    /** Preserve zero-valued codes and keep filtered lists as JSON arrays when signing. */
    protected function withoutEmptyValues(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->withoutEmptyValues($value);
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $result[$key] = $value;
            }
        }

        return array_is_list($data) ? array_values($result) : $result;
    }
}
