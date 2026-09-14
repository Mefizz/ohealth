<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Core\Arr;
use App\Classes\eHealth\EHealth;
use App\Models\Employee\Employee;
use App\Models\MedicalEvents\Sql\Condition;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Support\Facades\Log;

/**
 * TV 3.10.1 — resolve immutable care-plan fields from a finished encounter:
 * primary diagnosis → addresses, performer → author, reject patient mismatch.
 */
class CarePlanEncounterContextService
{
    /**
     * @return array{
     *     id: int|null,
     *     addresses: list<array<string, mixed>>,
     *     period_start: string|null,
     *     author_id: int|null,
     *     author_uuid: string|null,
     *     author: Employee|null,
     *     error: string|null
     * }
     */
    public function resolve(
        ?string $encounterUuid,
        int $expectedPersonId,
        ?string $patientUuid = null,
        ?string $conditionUuidFilter = null
    ): array {
        $empty = [
            'id' => null,
            'addresses' => [],
            'period_start' => null,
            'author_id' => null,
            'author_uuid' => null,
            'author' => null,
            'error' => null,
        ];

        if ($encounterUuid === null || trim($encounterUuid) === '') {
            $empty['error'] = __('care-plan.encounter_required');

            return $empty;
        }

        $encounter = Encounter::query()
            ->where('uuid', $encounterUuid)
            ->with(['diagnoses.condition', 'diagnoses.role.coding', 'performer', 'period'])
            ->first();

        if ($encounter === null) {
            $empty['error'] = __('care-plan.encounter_not_found');

            return $empty;
        }

        if ((int) $encounter->personId !== $expectedPersonId) {
            $empty['error'] = __('care-plan.encounter_patient_mismatch');

            return $empty;
        }

        $statusValue = $encounter->status instanceof \UnitEnum
            ? $encounter->status->value
            : (string) $encounter->status;

        // TV 3.10.1 — only finished, eHealth-confirmed encounters may seed a care plan.
        if ($encounter->ehealthInsertedAt === null || strtolower($statusValue) !== 'finished') {
            $empty['error'] = __('care-plan.encounter_must_be_finished');

            return $empty;
        }

        $author = $this->resolveAuthorFromPerformer($encounter);
        if ($author === null) {
            $empty['id'] = $encounter->id;
            $empty['error'] = __('care-plan.encounter_author_missing');

            return $empty;
        }

        $addresses = $this->resolvePrimaryAddresses(
            $encounter,
            $expectedPersonId,
            $patientUuid,
            $conditionUuidFilter
        );

        if ($addresses === []) {
            $empty['id'] = $encounter->id;
            $empty['author_id'] = $author->id;
            $empty['author_uuid'] = $author->uuid;
            $empty['author'] = $author;
            $empty['error'] = __('care-plan.encounter_primary_diagnosis_missing');

            return $empty;
        }

        $periodStart = null;
        if ($encounter->period) {
            $periodStart = $encounter->period->getRawOriginal('start');
        }

        return [
            'id' => $encounter->id,
            'addresses' => $addresses,
            'period_start' => $periodStart,
            'author_id' => $author->id,
            'author_uuid' => $author->uuid,
            'author' => $author,
            'error' => null,
        ];
    }

    private function resolveAuthorFromPerformer(Encounter $encounter): ?Employee
    {
        $performerUuid = $encounter->performer?->value;
        if ($performerUuid === null || $performerUuid === '') {
            return null;
        }

        return Employee::query()->where('uuid', $performerUuid)->first();
    }

    /**
     * Prefer diagnosis with role code `primary`; optionally narrow by condition UUID filter.
     *
     * @return list<array<string, mixed>>
     */
    private function resolvePrimaryAddresses(
        Encounter $encounter,
        int $personId,
        ?string $patientUuid,
        ?string $conditionUuidFilter
    ): array {
        $diagnoses = $encounter->diagnoses;

        $primary = $diagnoses->first(function ($diagnosis): bool {
            $code = $diagnosis->role?->coding?->first()?->code;

            return is_string($code) && strtolower($code) === 'primary';
        });

        $selected = $primary ? collect([$primary]) : collect();

        // Optional UI filter: keep primary only when it matches the requested condition.
        if ($conditionUuidFilter) {
            $selected = $selected->filter(
                fn ($d) => ($d->condition?->value ?? null) === $conditionUuidFilter
            );
            // If filter was supplied but primary does not match, fall back to that condition
            // only when it is explicitly the primary role — never all diagnoses.
            if ($selected->isEmpty()) {
                return [];
            }
        }

        // When no role=primary exists, do not invent addresses from secondary diagnoses.
        if ($selected->isEmpty()) {
            Log::warning('CarePlanEncounterContext: encounter has no primary diagnosis', [
                'encounter_id' => $encounter->id,
                'diagnoses_count' => $diagnoses->count(),
            ]);

            return [];
        }

        $addresses = [];
        foreach ($selected as $diagnosis) {
            $conditionUuid = $diagnosis->condition?->value;
            if (!$conditionUuid) {
                continue;
            }

            $actualCondition = Condition::query()
                ->where('uuid', $conditionUuid)
                ->with('code.coding')
                ->first();

            if (!$actualCondition && $patientUuid) {
                try {
                    $conditionData = EHealth::condition()->getById($patientUuid, $conditionUuid)->getData();
                    Repository::condition()->store([Arr::toCamelCase($conditionData)], $personId);
                    $actualCondition = Condition::query()
                        ->where('uuid', $conditionUuid)
                        ->with('code.coding')
                        ->first();
                } catch (\Throwable $e) {
                    Log::error('CarePlanEncounterContext: failed to fetch condition from eHealth', [
                        'condition_uuid' => $conditionUuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $coding = $actualCondition?->code?->coding?->first();
            if (!$coding) {
                continue;
            }

            $addresses[] = [
                'coding' => [
                    [
                        'system' => $coding->system,
                        'code' => $coding->code,
                    ],
                ],
            ];
        }

        return $addresses;
    }
}
