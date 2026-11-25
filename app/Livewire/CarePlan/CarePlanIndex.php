<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Enums\CarePlanStatusFilter;
use App\Enums\CarePlanCompletenessFilter;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanGetMany;
use App\Classes\eHealth\EHealth;
use App\Dto\CarePlan\Model as CarePlanData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Collection;

use App\Models\CarePlan;
use App\Repositories\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use App\Traits\HandlesCarePlanSync;
use Livewire\Attributes\Locked;
use Throwable;
use App\Jobs\CarePlanFullSync;

class CarePlanIndex extends Component
{
    use HandlesCarePlanSync;

    #[Locked]
    public array $syncBatchIds = [];

    #[Locked]
    public bool $syncInitiationFailed = false;

    public string $searchRequisition = '';

    public string $filterName = '';
    public string $filterEncounterId = '';
    public string $filterStatus = CarePlanStatusFilter::CURRENT->value;
    public string $filterCompleteness = CarePlanCompletenessFilter::COMPLETE->value;
    public string $filterStartDateRange = '';
    public string $filterEndDateRange = '';
    public bool $showAdditionalParams = false;

    public function search(): void
    {
        $this->searchByRequisition();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'searchRequisition',
            'filterName',
            'filterEncounterId',
            'filterStatus',
            'filterCompleteness',
            'filterStartDateRange',
            'filterEndDateRange',
        ]);
    }

    /**
     * Search eHealth by public requisition number (per TZ 3.10.3.2.1).
     */
    public function searchByRequisition(): void
    {
        if (empty($this->searchRequisition)) {
            return;
        }

        try {
            $facilityId = (int) legalEntity()->id;
            $repository = Repository::carePlan();
            $planApi = EHealth::carePlan();
            $activitiesApi = EHealth::carePlanActivity();
            $mapper = objectMapper();
            foreach ($this->collectSummaries(fn (int $page) => $planApi->getMany([
                'requisition' => $this->searchRequisition,
                'page' => $page,
            ])) as $summary) {
                $patient = $repository->resolvePatientContext(null, data_get($summary->native, 'subject.identifier.value'));
                $details = $planApi->getDetails($patient['uuid'], $summary->get('id'), typed: true)->validate();
                $plan = $mapper->map($details, CarePlanData::class);
                $plan->prepareResponse($details);
                $activities = $activitiesApi->allDetails($patient['uuid'], $plan->uuid);
                $repository->storeAggregate($plan, $activities, $patient['id'], $facilityId);
            }
            $this->filterCompleteness = CarePlanCompletenessFilter::ALL->value;
            if ($this->filterStatus === CarePlanStatusFilter::CURRENT->value) {
                $this->filterStatus = CarePlanStatusFilter::ALL->value;
            }
        } catch (Throwable $e) {
            Log::error('CarePlan search error: ' . $e->getMessage());
            session()->flash('error', __('care-plan.search_error') . ': ' . $e->getMessage());
        }
    }

    protected function collectSummaries(callable $fetch): CarePlanGetMany
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

    public function mount(): void
    {
        $this->syncBatchIds = $this->findRunningBatchesByLegalEntity(legalEntity()->id)
            ->filter(static fn ($batch): bool => str_starts_with($batch->name, CarePlanFullSync::BATCH_NAME.'_'))
            ->pluck('id')->all();
    }

    public function sync(): void
    {
        $user = Auth::user();
        if ($user?->can('getBySearchParams', CarePlan::class) !== true || $user->activeEmployee() === null) {
            session()->flash('error', __('care-plan.sync_access_denied'));

            return;
        }
        if ($this->syncBatchIds !== [] && $this->carePlanSyncOutcome($this->syncBatchIds) === null) {
            session()->flash('info', __('care-plan.sync_running'));

            return;
        }
        $this->syncBatchIds = [];
        $this->syncInitiationFailed = false;
        try {
            $persons = Repository::carePlan()->patientsForSync(legalEntity()->id, auth()->user());
            foreach ($persons->values() as $index => $person) {
                try {
                    $batchId = $this->startCarePlanPatientSync($person, firstPageSynchronously: $index === 0);
                    if ($batchId !== null) {
                        $this->syncBatchIds[] = $batchId;
                    }
                } catch (Throwable $exception) {
                    $this->syncInitiationFailed = true;
                    Log::error('Could not start patient CarePlan synchronization', ['exceptionType' => $exception::class]);
                }
            }
        } catch (Throwable $exception) {
            $this->syncInitiationFailed = true;
            Log::error('Could not start CarePlan index synchronization', ['exceptionType' => $exception::class]);
        }
        if ($this->syncInitiationFailed) {
            session()->flash('error', __('care-plan.sync_failed'));

            return;
        }
        session()->flash($this->syncBatchIds === [] ? 'success' : 'info', __($this->syncBatchIds === [] ? 'care-plan.sync_success' : 'care-plan.sync_first_page'));
    }

    public function checkSyncStatus(): void
    {
        if ($this->syncBatchIds === []) {
            return;
        }
        $outcome = $this->carePlanSyncOutcome($this->syncBatchIds);
        if ($outcome === null) {
            $this->skipRender();

            return;
        }
        $outcome['success'] = $outcome['success'] && !$this->syncInitiationFailed;
        $this->syncBatchIds = [];
        $this->flashCarePlanSyncOutcome($outcome);
    }

    #[Computed]
    public function carePlans(): Collection
    {
        return Repository::carePlan()->listing(legalEntity()->id, ['status' => $this->filterStatus, 'completeness' => $this->filterCompleteness, 'name' => $this->filterName, 'startDate' => $this->filterStartDateRange, 'endDate' => $this->filterEndDateRange, 'encounterId' => $this->filterEncounterId, 'requisition' => $this->searchRequisition]);
    }

    public function render()
    {
        return view('livewire.care-plan.care-plan-index');
    }
}
