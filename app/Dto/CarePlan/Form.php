<?php

declare(strict_types=1);

namespace App\Dto\CarePlan;

use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Models\CarePlan;
use App\Models\MedicalEvents\Sql\Identifier;
use App\Models\MedicalEvents\Sql\Period;
use App\Repositories\MedicalEvents\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;
use Symfony\Component\ObjectMapper\Condition\SourceClass;

#[Map(source: CarePlan::class, target: CarePlanForm::class)]
class Form
{
    // An absent scalar keeps the value initialized by the editor.
    #[Map(source: '[title?]', if: new IsNotNull())]
    public ?string $title = null;

    #[Map(source: '[description?]', if: new IsNotNull())]
    public ?string $description = null;

    #[Map(source: '[note?]', if: new IsNotNull())]
    public ?string $note = null;

    #[Map(source: '[intent?]', if: new IsNotNull())]
    public ?string $intent = null;

    #[Map(source: '[context?]', if: new IsNotNull())]
    public ?string $context = null;

    #[Map(source: '[informWith?]', if: new IsNotNull())]
    public ?string $informWith = null;

    #[Map(source: '[categoryConcept?][coding?][0?][code?]', if: new IsNotNull())]
    public string $category = '';

    #[Map(source: '[termsOfServiceConcept?][coding?][0?][code?]', if: new IsNotNull())]
    public string $termsOfService = '';

    #[Map(source: '[authorReference?][value?]', if: new IsNotNull())]
    public string $author = '';

    #[Map(source: '[encounterIdentifier?][value?]', if: new IsNotNull())]
    public string $encounter = '';

    #[Map(source: '[contributors]', if: new SourceClass(CarePlan::class), transform: [new self(), 'contributorValues'])]
    #[Map(if: new SourceClass(self::class))]
    public array $coAuthors = [];

    #[Map(source: '[supportingInfoReferences]', if: new SourceClass(CarePlan::class), transform: [new self(), 'supportingReferences'])]
    #[Map(target: 'episodes', if: new SourceClass(self::class), transform: [new self(), 'episodes'])]
    #[Map(target: 'medicalRecords', if: new SourceClass(self::class), transform: [new self(), 'medicalRecords'])]
    public array $supportingInfo = [];

    #[Map(source: '[effectivePeriod]', if: new SourceClass(CarePlan::class), transform: [new self(), 'periodStart'])]
    #[Map(target: 'periodStart', if: new SourceClass(self::class), transform: [new self(), 'date'])]
    #[Map(target: 'periodStartTime', if: new SourceClass(self::class), transform: [new self(), 'time'])]
    public ?CarbonImmutable $start = null;

    #[Map(source: '[effectivePeriod]', if: new SourceClass(CarePlan::class), transform: [new self(), 'periodEnd'])]
    #[Map(target: 'periodEnd', if: new SourceClass(self::class), transform: [new self(), 'date'])]
    #[Map(target: 'periodEndTime', if: new SourceClass(self::class), transform: [new self(), 'time'])]
    public ?CarbonImmutable $end = null;

    public function contributorValues(Collection $contributors): array
    {
        return $contributors->pluck('value')->values()->all();
    }

    public function supportingReferences(Collection $identifiers): array
    {
        return $identifiers->map(fn (Identifier $identifier): array => Repository::identifier()->toReference($identifier))->values()->all();
    }

    public function episodes(array $references): array
    {
        return collect($references)->where('identifier.type.coding.0.code', 'episode_of_care')
            ->map(fn (array $reference): array => ['uuid' => $reference['identifier']['value']])->values()->all();
    }

    public function medicalRecords(array $references): array
    {
        return collect($references)->where('identifier.type.coding.0.code', '!=', 'episode_of_care')->values()->all();
    }

    public function periodStart(?Period $period): ?CarbonImmutable
    {
        return $this->localDateTime($period?->getRawOriginal('start'));
    }

    public function periodEnd(?Period $period): ?CarbonImmutable
    {
        return $this->localDateTime($period?->getRawOriginal('end'));
    }

    protected function localDateTime(?string $utc): ?CarbonImmutable
    {
        // The model's display cast omits seconds, which the edit form must retain.
        return $utc === null ? null : CarbonImmutable::parse($utc, 'UTC')->setTimezone(config('app.timezone'));
    }

    public function date(?CarbonImmutable $date): string
    {
        return $date?->format(config('app.date_format', 'Y-m-d')) ?? '';
    }

    public function time(?CarbonImmutable $date): string
    {
        return $date?->format('H:i:s') ?? '';
    }
}
