<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CarePlan;
use Illuminate\Database\Eloquent\Collection;

class CarePlanRepository
{
    public function getByLegalEntity(int $legalEntityId): Collection
    {
        return CarePlan::where('legal_entity_id', $legalEntityId)
            ->with(['person', 'author.party'])
            ->latest()
            ->get();
    }

    public function getByPersonId(int $personId): Collection
    {
        return CarePlan::where('person_id', $personId)
            ->with(['person', 'author.party'])
            ->latest()
            ->get();
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
    public function formatCarePlanRequest(array $form, ?string $encounterUuid, array $encounterData, ?string $employeeUuid): array
    {
        return \App\Core\Arr::removeEmptyKeys([
            'intent' => 'order',
            'status' => 'new',
            'category' => $form['category'],
            'instantiates_protocol' => !empty($form['clinical_protocol']) ? [['display' => $form['clinical_protocol']]] : null,
            'context' => !empty($form['context']) ? ['identifier' => ['type_code' => $form['context']]] : null,
            'title' => $form['title'],
            'period' => array_filter([
                'start' => convertToYmd($form['period_start']),
                'end' => !empty($form['period_end']) ? convertToYmd($form['period_end']) : null,
            ]),
            'addresses' => $encounterData['addresses'] ?? null,
            'supporting_info' => array_merge(
                array_map(fn($e) => ['display' => $e['name']], $form['episodes'] ?? []),
                array_map(fn($m) => ['display' => $m['name']], $form['medical_records'] ?? [])
            ),
            'encounter' => !empty($form['encounter']) ? ['identifier' => ['value' => $form['encounter']]] : null,
            'care_manager' => ['identifier' => ['value' => $employeeUuid]],
            'description' => $form['description'] ?: null,
            'note' => $form['note'] ?: null,
            'inform_with' => $form['inform_with'] ?: null,
        ]);
    }
}
