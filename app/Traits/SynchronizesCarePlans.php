<?php

declare(strict_types=1);

namespace App\Traits;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Classes\eHealth\EHealth;
use App\Core\Arr;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait SynchronizesCarePlans
{
    protected function synchronizeResources(array $summaries, ?int $personId, int $legalEntityId, ?string $patientUuid = null, ?string $token = null): void
    {
        $summaries = $summaries['data'] ?? $summaries;
        $repository = Repository::carePlan();
        $planApi = EHealth::carePlan();
        foreach ($summaries as $summary) {
            $summary = Arr::toCamelCase($summary);
            $patient = $repository->resolvePatientContext($personId, $patientUuid ?? data_get($summary, 'subject.identifier.value'));
            if ($token !== null) {
                $planApi->withToken($token);
            }
            $details = $planApi->getDetails($patient['uuid'], $summary['id'], typed: true)->validate();
            $data = objectMapper()->map($details, CarePlanData::class);
            $data->prepareResponse($details);
            $activities = $this->fetchAllActivityResources($patient['uuid'], $data->uuid, token: $token);
            $repository->storeAggregate($data, $activities, $patient['id'], $legalEntityId);
        }
    }

    protected function synchronizePatient(string $patientUuid, int $personId, int $legalEntityId, array $query = []): void
    {
        $summaries = $this->collectSummaries(fn (int $page) => EHealth::carePlan()->getBySearchParams($patientUuid, array_replace($query, ['page' => $page]), typed: true));
        $this->synchronizeResources($summaries, $personId, $legalEntityId, $patientUuid);
    }

    protected function synchronizeSearch(array $query, int $legalEntityId): void
    {
        $summaries = $this->collectSummaries(fn (int $page) => EHealth::carePlan()->getMany(array_replace($query, ['page' => $page])));
        $this->synchronizeResources($summaries, null, $legalEntityId);
    }

    private function collectSummaries(callable $fetch): array
    {
        $summaries = [];
        $seen = [];
        for ($page = 1; ; $page++) {
            $response = $fetch($page);
            $items = $this->summaryResources($response->validate());
            foreach ($items as $item) {
                $item = Arr::toCamelCase($item);
                if (isset($seen[$item['id']])) {
                    throw ValidationException::withMessages(['paging' => 'Care Plan pagination repeated a resource.']);
                }
                $seen[$item['id']] = true;
                $summaries[] = $item;
            }
            $lastPage = $response->getPaging()['total_pages'] ?? null;
            if ($items === [] || ($lastPage !== null && $page >= (int) $lastPage)) {
                break;
            }
        }

        return $summaries;
    }

    /** Short entries supply discovery data; detail processing persists the full Model DTO. */
    protected function summaryResources(Collection|array $entries): array
    {
        return $entries instanceof Collection
            ? $entries->map(static fn (CarePlanDetails $entry): array => $entry->toResource())->all()
            : $entries;
    }

    protected function fetchAllActivityResources(string $patientUuid, string $planUuid, array $query = [], ?string $token = null): array
    {
        $api = EHealth::carePlanActivity();
        if ($token !== null) {
            $api->withToken($token);
        }
        $resources = [];
        for ($page = 1; ; $page++) {
            $response = $api->getSummary($patientUuid, $planUuid, array_replace($query, ['page' => $page]));
            $items = $response->validate();
            foreach ($items as $item) {
                $id = Arr::toCamelCase($item)['id'];
                if (isset($resources[$id])) {
                    throw ValidationException::withMessages(['paging' => 'Activity pagination repeated a resource.']);
                }
                $resource = $api->getDetails($patientUuid, $planUuid, $id)->validate();
                if ($resource['id'] !== $id) {
                    throw ValidationException::withMessages(['id' => 'Activity details do not match the requested resource.']);
                }
                $resources[$id] = $resource;
            }
            $lastPage = $response->getPaging()['total_pages'] ?? null;
            if ($items === [] || ($lastPage !== null && $page >= (int) $lastPage)) {
                break;
            }
        }

        return array_values($resources);
    }
}
