<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\CarePlanStatus;
use App\Classes\eHealth\EHealth;
use App\Models\CarePlan;
use App\Repositories\MedicalEvents\Repository as MedicalEventsRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\MedicalEvents\Concerns\PersistsCarePlanFhir;
use App\Models\Employee\Employee;
use App\Models\MedicalEvents\Sql\Encounter;
use InvalidArgumentException;
use Illuminate\Support\Str;
use App\Services\MedicalEvents\Fhir;
use App\Core\Arr;
use Throwable;

class CarePlanRepository
{
    use PersistsCarePlanFhir;

    /** Registered legacy resources require a full remote sync, not a guess from lossy columns. */
    public function backfillResources(int $legalEntityId, bool $write = false): array
    {
        $report = ['plans' => 0, 'activities' => 0, 'converted' => 0, 'needsRemoteSync' => [], 'errors' => []];
        CarePlan::whereLegalEntityId($legalEntityId)->with('activities')->chunkById(100, function ($plans) use (&$report): void {
            foreach ($plans as $plan) {
                $report['plans']++;
                $report['activities'] += $plan->activities->count();
                if (\App\Models\CarePlanActivity::whereCarePlanId($plan->id)->whereNotNull('uuid')->select('uuid')->groupBy('uuid')->havingRaw('COUNT(*) > 1')->exists()) {
                    $report['errors'][] = ['type' => 'duplicateActivity', 'localId' => $plan->id, 'reason' => 'Duplicate activity UUIDs must be reconciled before schema migration.'];
                }
                if ((int) ($plan->getAttributes()['fhir_version'] ?? 0) !== 1 && $plan->uuid !== null && $plan->status !== CarePlanStatus::DRAFT->value) {
                    $report['needsRemoteSync'][] = ['type' => 'carePlan', 'localId' => $plan->id];
                    continue;
                }
                try {
                    if ((int) ($plan->getAttributes()['fhir_version'] ?? 0) !== 1) {
                        $this->legacyDraftResource($plan);
                    }
                    foreach ($plan->activities as $activity) {
                        if ((int) ($activity->getAttributes()['fhir_version'] ?? 0) === 1) {
                            continue;
                        }
                        if ($activity->uuid !== null && $activity->status !== 'draft') {
                            $report['needsRemoteSync'][] = ['type' => 'activity', 'localId' => $activity->id];
                        } else {
                            app(CarePlanActivityRepository::class)->legacyDraftResource($activity, $plan);
                        }
                    }
                } catch (Throwable $exception) {
                    $report['errors'][] = ['type' => 'carePlan', 'localId' => $plan->id, 'reason' => $exception->getMessage()];
                }
            }
        });
        if (CarePlan::whereLegalEntityId($legalEntityId)->whereNotNull('uuid')->select('person_id', 'uuid')->groupBy('person_id', 'uuid')->havingRaw('COUNT(*) > 1')->exists()) {
            $report['errors'][] = ['type' => 'duplicateCarePlan', 'reason' => 'Duplicate resource UUIDs must be reconciled before schema migration.'];
        }
        if (!$write || $report['errors'] !== [] || $report['needsRemoteSync'] !== []) {
            return $report;
        }
        CarePlan::whereLegalEntityId($legalEntityId)->chunkById(100, function ($plans) use (&$report, $legalEntityId): void {
            foreach ($plans as $plan) {
                DB::transaction(function () use ($plan, $legalEntityId, &$report): void {
                    $plan = CarePlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
                    if ($plan->fhirVersion !== 1) {
                        $plan = $this->storeResource($this->legacyDraftResource($plan), $plan->personId, $legalEntityId, $plan->id);
                        $report['converted']++;
                    }
                    $repository = app(CarePlanActivityRepository::class);
                    foreach ($plan->activities()->get() as $activity) {
                        if ($activity->fhirVersion !== 1) {
                            $repository->storeResource($repository->legacyDraftResource($activity, $plan), $plan, $activity->id);
                            $report['converted']++;
                        }
                    }
                });
            }
        });

        return $report;
    }

    private function legacyDraftResource(CarePlan $plan): array
    {
        $plan->loadMissing(['author', 'effectivePeriod', 'categoryConcept.coding', 'encounterIdentifier.type.coding', 'supportingInfoReferences.type.coding']);
        $data = Arr::toCamelCase($plan->getAttributes());
        $data['uuid'] = $plan->uuid ?? Str::uuid()->toString();
        $data['category'] = $plan->categoryConcept ? $this->fhirConcept($plan->categoryConcept) : $plan->category;
        $data['author'] = $plan->author?->uuid;
        if (!$data['author']) {
            throw new InvalidArgumentException('Draft author cannot be resolved.');
        }
        $data['addresses'] = $plan->addresses ?? [];
        $data['supportingInfo'] = $plan->supportingInfoReferences->map(fn ($reference): array => $this->fhirReference($reference))->all();
        if ($data['supportingInfo'] === [] && !empty($plan->supportingInfo)) {
            throw new InvalidArgumentException('Legacy supporting info requires explicit reconciliation.');
        }
        if ($plan->encounterIdentifier) {
            $data['encounter'] = $this->fhirReference($plan->encounterIdentifier);
        }
        if ($plan->effectivePeriod) {
            $data['period'] = MedicalEventsRepository::period()->toResource($plan->effectivePeriod);
        }

        return Fhir::carePlan()->toFhir($data, ['timezone' => config('app.timezone')]);
    }

    /** Store a complete, normalized resource. This method performs no remote requests. */
    public function storeResource(array $resource, int $personId, int $legalEntityId, ?int $localId = null): CarePlan
    {
        if (!isset($resource['id'], $resource['title'], $resource['status'], $resource['period']['start'])) {
            throw new InvalidArgumentException('A complete Care Plan requires id, title, status and period.start.');
        }

        return DB::transaction(function () use ($resource, $personId, $legalEntityId, $localId): CarePlan {
            $query = CarePlan::wherePersonId($personId)->whereLegalEntityId($legalEntityId)->lockForUpdate();
            $plan = $localId === null ? $query->whereUuid($resource['id'])->first() : $query->findOrFail($localId);
            if ($plan?->uuid !== null && $plan->uuid !== $resource['id']) {
                throw new InvalidArgumentException('Cannot replace an existing Care Plan identity.');
            }
            $plan ??= new CarePlan(['personId' => $personId, 'legalEntityId' => $legalEntityId]);
            $authorUuid = data_get($resource, 'author.identifier.value');
            $encounterUuid = data_get($resource, 'encounter.identifier.value');
            $attributes = [
                'uuid' => $resource['id'],
                'status' => $resource['status'],
                'title' => $resource['title'],
                'intent' => $resource['intent'] ?? null,
                'description' => $resource['description'] ?? null,
                'note' => $resource['note'] ?? null,
                'addresses' => $resource['addresses'] ?? [],
                'requisition' => $resource['requisition'] ?? null,
                'authorId' => $authorUuid ? Employee::whereUuid($authorUuid)->whereLegalEntityId($legalEntityId)->value('id') : null,
                'encounterId' => $encounterUuid ? Encounter::whereUuid($encounterUuid)->value('id') : null,
                'fhirVersion' => 1,
            ];
            foreach (['category' => ['categoryConcept', 'categoryId'], 'termsOfService' => ['termsOfServiceConcept', 'termsOfServiceConceptId']] as $field => [$relation, $attribute]) {
                $attributes[$attribute] = $this->replaceFhirConcept($plan->exists ? $plan->{$relation} : null, $resource[$field] ?? null);
            }
            foreach (['author' => ['authorReference', 'authorReferenceId'], 'encounter' => ['encounterIdentifier', 'encounterIdentifierId'], 'careManager' => ['careManager', 'careManagerId'], 'managingOrganization' => ['managingOrganization', 'managingOrganizationId']] as $field => [$relation, $attribute]) {
                $attributes[$attribute] = $this->replaceFhirReference($plan->exists ? $plan->{$relation} : null, $resource[$field] ?? null);
            }
            $known = ['id', 'status', 'title', 'intent', 'description', 'note', 'addresses', 'requisition', 'category', 'termsOfService', 'author', 'encounter', 'careManager', 'managingOrganization', 'period', 'supportingInfo', 'contributor'];
            $attributes['fhirMetadata'] = array_diff_key($resource, array_flip($known));
            $plan->fill($attributes)->save();
            MedicalEventsRepository::period()->syncExact($plan, $resource['period']);
            $this->syncFhirCollection($plan, 'supportingInfoReferences', $resource['supportingInfo'] ?? []);
            $this->syncFhirCollection($plan, 'contributors', $resource['contributor'] ?? [], positioned: true);

            return $plan;
        });
    }

    public function getResource(int $id): array
    {
        $plan = CarePlan::with(['categoryConcept.coding', 'termsOfServiceConcept.coding', 'authorReference.type.coding', 'encounterIdentifier.type.coding', 'careManager.type.coding', 'managingOrganization.type.coding', 'effectivePeriod', 'supportingInfoReferences.type.coding', 'contributors.type.coding'])->findOrFail($id);
        if ($plan->fhirVersion !== 1) {
            throw new InvalidArgumentException('Care Plan must pass the FHIR backfill before use.');
        }
        $resource = array_replace($plan->fhirMetadata ?? [], [
            'id' => $plan->uuid,
            'status' => $plan->status,
            'title' => $plan->title,
            'intent' => $plan->intent,
            'description' => $plan->description,
            'note' => $plan->note,
            'addresses' => $plan->addresses ?? [],
            'requisition' => $plan->requisition,
            'period' => $plan->effectivePeriod ? MedicalEventsRepository::period()->toResource($plan->effectivePeriod) : null,
            'supportingInfo' => $plan->supportingInfoReferences->map(fn ($reference): array => $this->fhirReference($reference))->all(),
            'contributor' => $plan->contributors->map(fn ($reference): array => $this->fhirReference($reference))->all(),
        ]);
        foreach (['category' => 'categoryConcept', 'termsOfService' => 'termsOfServiceConcept'] as $field => $relation) {
            $resource[$field] = $this->fhirConcept($plan->{$relation});
        }
        foreach (['author' => 'authorReference', 'encounter' => 'encounterIdentifier', 'careManager' => 'careManager', 'managingOrganization' => 'managingOrganization'] as $field => $relation) {
            $resource[$field] = $this->fhirReference($plan->{$relation});
        }

        return array_filter($resource, static fn (mixed $value): bool => $value !== null);
    }

    public function getByLegalEntity(int $legalEntityId): Collection
    {
        return CarePlan::where('legal_entity_id', $legalEntityId)
            ->with(['person', 'person.names', 'author.party', 'encounter.episode', 'encounter.diagnoses.condition', 'encounterIdentifier'])
            ->latest()
            ->get();
    }

    public function getByPersonId(int $personId, array $filters = []): Collection
    {
        $query = CarePlan::where('person_id', $personId)
            ->with(['person', 'person.names', 'author.party', 'encounter.episode', 'encounter.diagnoses.condition', 'encounterIdentifier']);

        if (!empty($filters['name'])) {
            $query->where('title', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['status'])) {
            $query->whereRaw('LOWER(status) = LOWER(?)', [$filters['status']]);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('period_start', '>=', \Carbon\Carbon::parse($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('period_end', '<=', \Carbon\Carbon::parse($filters['end_date']));
        }

        if (!empty($filters['encounter_id'])) {
            $query->where('encounter_id', 'like', "%{$filters['encounter_id']}%");
        }

        return $query->latest()->get();
    }

    public function findById(int $id): ?CarePlan
    {
        return CarePlan::with(['person', 'author.party', 'activities'])->find($id);
    }

    public function findByUuid(string $uuid): ?CarePlan
    {
        return CarePlan::with(['person', 'author.party', 'activities'])->where('uuid', $uuid)->first();
    }

    public function create(array $data): CarePlan
    {
        return CarePlan::create($data);
    }

    public function update(CarePlan $carePlan, array $data): bool
    {
        return $carePlan->update($data);
    }

    public function updateById(int $id, array $data): bool
    {
        $carePlan = CarePlan::find($id);
        if (!$carePlan) {
            return false;
        }

        return $carePlan->update($data);
    }

    /**
     * Format Care Plan data into the proper FHIR schema for eHealth API requests.
     */
    public function formatCarePlanRequest(array $form, ?string $encounterUuid, array $encounterData, ?string $employeeUuid, ?string $carePlanUuid = null): array
    {
        $id = $carePlanUuid ?: \Illuminate\Support\Str::uuid()->toString();

        $addresses = $encounterData['addresses'] ?? [];

        $employeeRef = [
            'identifier' => [
                'type' => [
                    'coding' => [['system' => 'eHealth/resources', 'code' => 'employee']]
                ],
                'value' => $employeeUuid
            ]
        ];

        // Use encounter period start if available to satisfy eHealth rule:
        // "Care plan start date must be greater or equal than Encounter period start"
        $periodStart = $form['periodStart'] ?? $form['period_start'];
        if (!empty($encounterData['period_start'])) {
            // Encounter start is already in UTC from DB
            $encounterStart = \Carbon\CarbonImmutable::parse($encounterData['period_start'], 'UTC');

            // Form date is Kyiv time (e.g. "12.05.2026")
            $formStart = \Carbon\CarbonImmutable::parse($periodStart, config('app.timezone', 'Europe/Kyiv'))->startOfDay();

            // If the selected day is the same or earlier than the encounter's day,
            // we MUST use the encounter's actual start time to avoid the 422 error.
            if ($formStart->utc()->lt($encounterStart)) {
                // If user picked today but encounter started later today,
                // we set care plan start exactly to encounter start + 1 minute for safety
                $periodStart = $encounterStart->addMinute()->toDateTimeString();

                // Since convertToEHealthISO8601 parses and converts to UTC again,
                // we need to pass it a format it understands or bypass it.
                // To keep it simple, we'll use a direct ISO string if we already have UTC.
                $finalPeriodStart = $encounterStart->addMinute()->toIso8601ZuluString();
            } else {
                $finalPeriodStart = convertToEHealthISO8601($periodStart . ' 00:00:00');
            }
        } else {
            $finalPeriodStart = convertToEHealthISO8601($periodStart . ' 00:00:00');
        }

        $payload = removeEmptyKeys([
            'id' => $id,
            'intent' => 'order',
            'status' => 'new',
            'category' => [
                'coding' => [
                    ['system' => 'eHealth/care_plan_categories', 'code' => $form['category']]
                ]
            ],
            'title' => $form['title'],
            'period' => array_filter([
                'start' => $finalPeriodStart,
                'end' => !empty($form['periodEnd']) ? convertToEHealthISO8601($form['periodEnd'] . ' 23:59:59') : (!empty($form['period_end']) ? convertToEHealthISO8601($form['period_end'] . ' 23:59:59') : null),
            ]),
            'addresses' => !empty($addresses) ? array_values($addresses) : null,
            'supporting_info' => array_values(array_filter(array_map(fn ($e) =>
                (!empty($e['uuid']) || !empty($e['id'])) ? [
                    'identifier' => [
                        'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'episode_of_care']]],
                        'value' => $e['uuid'] ?? $e['id']
                    ]
                ] : null, $form['episodes'] ?? []))),
            'encounter' => !empty($form['encounter']) ? [
                'identifier' => [
                    'type' => [
                        'coding' => [['system' => 'eHealth/resources', 'code' => 'encounter']]
                    ],
                    'value' => $form['encounter']
                ]
            ] : null,
            'author' => $employeeRef,
            'description' => $form['description'] ?: null,
            'note' => $form['note'] ?: null,
            'terms_of_service' => [
                'coding' => [
                    ['system' => 'PROVIDING_CONDITION', 'code' => $form['termsOfService'] ?? $form['terms_of_service']]
                ]
            ],
            'inform_with' => $form['informWith'] ?? ($form['inform_with'] ?? null)
        ]);

        return $payload;
    }

    /**
     * Resolve care plan period bounds as stored in eHealth (UTC).
     *
     * @return array{start: ?\Carbon\CarbonInterface, end: ?\Carbon\CarbonInterface}
     */
    public function resolveEHealthPeriodBounds(CarePlan $carePlan): array
    {
        $carePlan->loadMissing(['effectivePeriod', 'encounter.period']);

        if ($carePlan->effectivePeriod) {
            $rawStart = $this->periodValueAsUtcString($carePlan->effectivePeriod, 'start');
            $rawEnd = $this->periodValueAsUtcString($carePlan->effectivePeriod, 'end');

            return [
                'start' => $rawStart ? \Carbon\Carbon::parse($rawStart, 'UTC') : null,
                'end' => $rawEnd ? \Carbon\Carbon::parse($rawEnd, 'UTC') : null,
            ];
        }

        $periodStartForm = $carePlan->period_start?->format('Y-m-d');
        $encounterRawStart = $carePlan->encounter?->period?->getRawOriginal('start');

        if ($encounterRawStart && $periodStartForm) {
            $encounterStart = \Carbon\CarbonImmutable::parse($encounterRawStart, 'UTC');
            $formStart = \Carbon\CarbonImmutable::parse($periodStartForm, config('app.timezone', 'Europe/Kyiv'))->startOfDay();

            if ($formStart->utc()->lt($encounterStart)) {
                $start = \Carbon\Carbon::parse($encounterStart->addMinute()->toIso8601ZuluString())->utc();
            } else {
                $start = \Carbon\Carbon::parse(convertToEHealthISO8601($periodStartForm . ' 00:00:00'))->utc();
            }
        } elseif ($periodStartForm) {
            $start = \Carbon\Carbon::parse(convertToEHealthISO8601($periodStartForm . ' 00:00:00'))->utc();
        } else {
            $start = null;
        }

        $end = null;
        if ($carePlan->period_end) {
            $end = \Carbon\Carbon::parse(
                convertToEHealthISO8601($carePlan->period_end->format('Y-m-d') . ' 23:59:59')
            )->utc();
        }

        return ['start' => $start, 'end' => $end];
    }

    private function periodValueAsUtcString(\App\Models\MedicalEvents\Sql\Period $period, string $key): ?string
    {
        $raw = $period->getRawOriginal($key);
        if (!empty($raw)) {
            return (string) $raw;
        }

        $value = $period->getAttributes()[$key] ?? null;
        if (empty($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $validatedData
     * @param  int|null  $fallbackAuthorId  Employee used when the remote author is unknown locally.
     *                                      Care plans require an author, so plans stay unsynced
     *                                      without one rather than being attributed to a guess.
     */
    public function syncCarePlans(array $validatedData, ?int $personId = null, ?int $fallbackAuthorId = null): void
    {
        $activityRepo = app(CarePlanActivityRepository::class);
        $plans = isset($validatedData['data']) ? $validatedData['data'] : $validatedData;

        foreach ($plans as $rawFhir) {
            $person = null;

            if ($personId) {
                $person = \App\Models\Person\Person::find($personId);
            } else {
                // Try to find person by subject identifier (patient UUID)
                $patientUuid = $rawFhir['subject']['identifier']['value'] ?? null;
                if ($patientUuid) {
                    $person = \App\Models\Person\Person::where('uuid', $patientUuid)->first();
                }
            }

            if (!$person) {
                \Illuminate\Support\Facades\Log::warning('CarePlanRepository: person not found for CarePlan sync', [
                    'care_plan_uuid' => $rawFhir['id'],
                    'patient_uuid' => $rawFhir['subject']['identifier']['value'] ?? 'missing'
                ]);
                continue;
            }

            // TODO: Move raw FHIR data storage to MongoDB when the driver and collection are ready.
            // Currently disabled to prevent conflicts with the SQL 'care_plans' table.
            /*
            \App\Models\MedicalEvents\Mongo\CarePlan::updateOrCreate(
                ['uuid' => $rawFhir['uuid']],
                ['data' => $rawFhir]
            );
            */

            DB::transaction(function () use ($person, $rawFhir, $activityRepo, $fallbackAuthorId) {
                $categoryData = isset($rawFhir['category']) && is_array($rawFhir['category'])
                    ? ($rawFhir['category'][0] ?? null)
                    : ($rawFhir['category'] ?? null);

                $category = $categoryData
                    ? MedicalEventsRepository::codeableConcept()->store($categoryData)
                    : null;

                $encounterIdentifier = isset($rawFhir['encounter']['identifier']['value'])
                    ? MedicalEventsRepository::identifier()->store($rawFhir['encounter']['identifier']['value'])
                    : null;

                if ($encounterIdentifier && isset($rawFhir['encounter']['identifier']['type'])) {
                    MedicalEventsRepository::codeableConcept()->attach($encounterIdentifier, $rawFhir['encounter']);
                }

                $careManager = isset($rawFhir['careManager']['identifier']['value'])
                    ? MedicalEventsRepository::identifier()->store($rawFhir['careManager']['identifier']['value'])
                    : null;

                if ($careManager && isset($rawFhir['careManager']['identifier']['type'])) {
                    MedicalEventsRepository::codeableConcept()->attach($careManager, $rawFhir['careManager']);
                }

                $managingOrganization = isset($rawFhir['managing_organization']['identifier']['value'])
                    ? MedicalEventsRepository::identifier()->store(
                        $rawFhir['managing_organization']['identifier']['value'],
                        $rawFhir['managing_organization']['display_value'] ?? null
                    )
                    : null;

                if ($managingOrganization && isset($rawFhir['managing_organization']['identifier']['type'])) {
                    MedicalEventsRepository::codeableConcept()->attach($managingOrganization, $rawFhir['managing_organization']);
                }

                $author = null;
                $authorUuid = $rawFhir['author']['identifier']['value'] ?? null;
                if ($authorUuid) {
                    $author = \App\Models\Employee\Employee::where('uuid', $authorUuid)->first();
                }

                $authorId = $author?->id ?? $fallbackAuthorId;

                if (!$authorId) {
                    \Illuminate\Support\Facades\Log::warning('CarePlanRepository: author not found for CarePlan sync', [
                        'care_plan_uuid' => $rawFhir['id'] ?? null,
                        'author_uuid' => $authorUuid,
                    ]);

                    return;
                }

                $addresses = [];
                if (isset($rawFhir['addresses']) && is_array($rawFhir['addresses'])) {
                    foreach ($rawFhir['addresses'] as $addr) {
                        if (isset($addr['coding']) && is_array($addr['coding'])) {
                            $addresses[] = $addr;
                        } elseif (isset($addr['reference']) && str_starts_with($addr['reference'], 'Condition/')) {
                            $conditionUuid = str_replace('Condition/', '', $addr['reference']);
                            $actualCondition = \App\Models\MedicalEvents\Sql\Condition::where('uuid', $conditionUuid)->with('code.coding')->first();
                            if ($actualCondition) {
                                $coding = $actualCondition->code?->coding?->first();
                                if ($coding) {
                                    $addresses[] = [
                                        'coding' => [
                                            [
                                                'system' => $coding->system,
                                                'code' => $coding->code
                                            ]
                                        ]
                                    ];
                                }
                            }
                        }
                    }
                }

                // Try to find existing record by UUID OR by (person + encounter) if UUID is missing locally
                $carePlan = CarePlan::where('uuid', $rawFhir['id'] ?? $rawFhir['uuid'] ?? null)->first();
                if (!$carePlan && $encounterIdentifier) {
                    $carePlan = CarePlan::where('person_id', $person->id)
                        ->where('encounter_identifier_id', $encounterIdentifier->id)
                        ->whereNull('uuid')
                        ->first();
                }

                $localEncounterId = null;
                if ($encounterIdentifier) {
                    $localEncounter = \App\Models\MedicalEvents\Sql\Encounter::where('uuid', $encounterIdentifier->value)->first();
                    $localEncounterId = $localEncounter?->id;
                }

                if ($carePlan) {
                    $carePlan->update([
                        'uuid' => $rawFhir['id'] ?? $rawFhir['uuid'] ?? null,
                        'author_id' => $authorId,
                        'legal_entity_id' => $person->legal_entity_id ?? legalEntity()->id,
                        'status' => $rawFhir['status'] ?? CarePlanStatus::ACTIVE->value,
                        'title' => !empty($rawFhir['title']) ? $rawFhir['title'] : ($carePlan->title ?? 'План лікування'),
                        'description' => !empty($rawFhir['description']) ? $rawFhir['description'] : ($carePlan->description ?? null),
                        'note' => !empty($rawFhir['note']) ? $rawFhir['note'] : ($carePlan->note ?? null),
                        'category_id' => $category?->id,
                        'encounter_identifier_id' => $encounterIdentifier?->id,
                        'encounter_id' => $localEncounterId ?? $carePlan->encounter_id,
                        'care_manager_id' => $careManager?->id,
                        'managing_organization_id' => $managingOrganization?->id ?? $carePlan->managingOrganizationId,
                        'period_start' => isset($rawFhir['period']['start'])
                            ? \Carbon\Carbon::parse($rawFhir['period']['start'])
                            : ($rawFhir['ehealth_inserted_at'] ?? now()),
                        'period_end' => isset($rawFhir['period']['end'])
                            ? \Carbon\Carbon::parse($rawFhir['period']['end'])
                            : null,
                        'terms_of_service' => $rawFhir['terms_of_service']['coding'][0]['code'] ?? null,
                        'addresses' => !empty($addresses) ? $addresses : ($carePlan->addresses ?? null),
                    ]);
                } else {
                    $carePlan = CarePlan::create([
                        'uuid' => $rawFhir['id'] ?? $rawFhir['uuid'] ?? null,
                        'person_id' => $person->id,
                        'author_id' => $authorId,
                        'legal_entity_id' => $person->legal_entity_id ?? (legalEntity()?->id ?? null),
                        'status' => $rawFhir['status'] ?? CarePlanStatus::ACTIVE->value,
                        'title' => !empty($rawFhir['title']) ? $rawFhir['title'] : 'План лікування',
                        'description' => !empty($rawFhir['description']) ? $rawFhir['description'] : null,
                        'note' => !empty($rawFhir['note']) ? $rawFhir['note'] : null,
                        'category_id' => $category?->id,
                        'encounter_identifier_id' => $encounterIdentifier?->id,
                        'encounter_id' => $localEncounterId,
                        'care_manager_id' => $careManager?->id,
                        'managing_organization_id' => $managingOrganization?->id,
                        'period_start' => isset($rawFhir['period']['start'])
                            ? \Carbon\Carbon::parse($rawFhir['period']['start'])
                            : ($rawFhir['ehealth_inserted_at'] ?? now()),
                        'period_end' => isset($rawFhir['period']['end'])
                            ? \Carbon\Carbon::parse($rawFhir['period']['end'])
                            : null,
                        'terms_of_service' => $rawFhir['terms_of_service']['coding'][0]['code'] ?? null,
                        'addresses' => !empty($addresses) ? $addresses : null,
                    ]);
                }

                if (isset($rawFhir['period'])) {
                    MedicalEventsRepository::period()->sync($carePlan, $rawFhir['period'], 'effectivePeriod');
                }

                if (isset($rawFhir['supportingInfo'])) {
                    $supportingInfoIds = [];
                    foreach ($rawFhir['supportingInfo'] as $info) {
                        $identifier = MedicalEventsRepository::identifier()->store($info['identifier']['value']);
                        if (isset($info['identifier']['type'])) {
                            MedicalEventsRepository::codeableConcept()->attach($identifier, $info);
                        }
                        $supportingInfoIds[] = $identifier->id;
                    }
                    $carePlan->supportingInfoReferences()->sync($supportingInfoIds);
                }

                // Trigger sync for activities directly for each plan found active or relevant
                $activityRepo->syncActivities($person, $carePlan);
            });
        }
    }
}
