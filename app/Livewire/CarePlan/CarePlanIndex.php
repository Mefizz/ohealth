<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Classes\eHealth\EHealth;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Enums\CarePlanCompletenessFilter;
use App\Enums\CarePlanStatusFilter;
use App\Jobs\CarePlanFullSync;
use App\Models\CarePlan;
use App\Repositories\Repository;
use App\Traits\HandlesCarePlanSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

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
        if (Auth::user()?->can('viewAny', CarePlan::class) !== true) {
            session()->flash('error', __('care-plan.sync_access_denied'));

            return;
        }

        try {
            $this->synchronizeSearch(['requisition' => $this->searchRequisition], (int) legalEntity()->id);
            $this->filterCompleteness = CarePlanCompletenessFilter::ALL->value;
            if ($this->filterStatus === CarePlanStatusFilter::CURRENT->value) {
                $this->filterStatus = CarePlanStatusFilter::ALL->value;
            }
        } catch (Throwable $e) {
            Log::error('CarePlan search error: ' . $e->getMessage());
            session()->flash('error', __('care-plan.search_error') . ': ' . $e->getMessage());
        }
    }

    protected function synchronizeSearch(array $query, int $legalEntityId): void
    {
        $api = EHealth::carePlan();
        $repository = Repository::carePlan();
        foreach ($api->getRequisitionResults($query) as $summary) {
            $patient = $repository->resolvePatientContext(null, data_get($summary->native, 'subject.identifier.value'));
            $details = $api->getDetails($patient['uuid'], $summary->get('id'), typed: true)->validate();
            $data = objectMapper()->map($details, CarePlanData::class);
            $data->prepareResponse($details);
            $activities = $api->getActivityResources($patient['uuid'], $data->uuid);
            $repository->storeAggregate($data, $activities, $patient['id'], $legalEntityId);
        }
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('viewAny', CarePlan::class), 404);

        $this->syncBatchIds = $this->findRunningBatchesByLegalEntity(legalEntity()->id)
            ->filter(static fn ($batch): bool => str_starts_with($batch->name, CarePlanFullSync::BATCH_NAME.'_'))
            ->pluck('id')->all();
    }

    public function sync(): void
    {
        // TODO: Share the employee precondition across the CarePlan sync entry points.
        $user = Auth::user();
        if ($user === null || $user->cannot('viewAny', CarePlan::class) || $user->activeEmployee() === null) {
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
            $persons = Repository::carePlan()->patientsForSync(legalEntity()->id, $user);
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
        abort_unless(Auth::user()?->can('viewAny', CarePlan::class), 404);

        return Repository::carePlan()->listing(legalEntity()->id, ['status' => $this->filterStatus, 'completeness' => $this->filterCompleteness, 'name' => $this->filterName, 'startDate' => $this->filterStartDateRange, 'endDate' => $this->filterEndDateRange, 'encounterId' => $this->filterEncounterId, 'requisition' => $this->searchRequisition]);
    }

    public function render()
    {
        return view('livewire.care-plan.care-plan-index');
    }
}
