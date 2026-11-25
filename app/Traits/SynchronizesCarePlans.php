<?php

declare(strict_types=1);

namespace App\Traits;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanGetMany;
use App\Classes\eHealth\EHealth;
use App\Core\Arr;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Repositories\Repository;
use Illuminate\Validation\ValidationException;

trait SynchronizesCarePlans
{
    protected function synchronizeResources(
        array|CarePlanGetMany $summaries,
        ?int $personId,
        int $legalEntityId,
        ?string $patientUuid = null,
        ?string $token = null
    ): void {
        $items = $summaries instanceof CarePlanGetMany ? $summaries : ($summaries['data'] ?? $summaries);
        $repository = Repository::carePlan();
        $planApi = EHealth::carePlan();
        if ($token !== null) {
            $planApi->withToken($token);
        }
        foreach ($items as $item) {
            $summary = $item instanceof CarePlanDetails ? $item->native : $item;
            $patient = $repository->resolvePatientContext($personId, $patientUuid ?? data_get($summary, 'subject.identifier.value'));
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

    private function collectSummaries(callable $fetch): CarePlanGetMany
    {
        $summaries = new CarePlanGetMany();
        $seen = [];
        for ($page = 1; ; $page++) {
            $response = $fetch($page);
            $items = $response->validate();
            foreach ($items as $item) {
                $id = $item->get('id');
                if (isset($seen[$id])) {
                    throw ValidationException::withMessages(['paging' => 'Care Plan pagination repeated a resource.']);
                }
                $seen[$id] = true;
                $summaries->push($item);
            }
            $lastPage = $response->getPaging()['total_pages'] ?? null;
            if ($items->isEmpty() || ($lastPage !== null && $page >= (int) $lastPage)) {
                break;
            }
        }

        return $summaries;
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
