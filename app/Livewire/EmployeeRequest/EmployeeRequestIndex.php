<?php

declare(strict_types=1);

namespace App\Livewire\EmployeeRequest;

use Auth;
use Exception;
use Throwable;
use App\Models\User;
use App\Enums\JobStatus;
use Illuminate\Bus\Batch;
use App\Models\LegalEntity;
use Livewire\WithPagination;
use App\Classes\eHealth\EHealth;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Enums\Employee\RequestStatus;
use App\Jobs\EmployeeRequestsSyncAll;
use Illuminate\Support\Facades\Session;
use App\Traits\BatchLegalEntityQueries;
use App\Models\Employee\EmployeeRequest;
use App\Livewire\Employee\EmployeeComponent;
use App\Livewire\Employee\Concerns\DeletesEmployeeRequestDraft;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Notifications\EmployeeRequestSyncCompleted;
use App\Services\Employee\EmployeeRequestProcessor;
use App\Notifications\SyncNotification;

class EmployeeRequestIndex extends EmployeeComponent
{
    use WithPagination;
    use BatchLegalEntityQueries;
    use DeletesEmployeeRequestDraft;

    protected const string BATCH_NAME = 'EmployeeRequestsSyncAll';
    protected const string DEPENDENT_BATCH_NAME = 'EmployeeRequestDetailsSync';

    public string $search = '';
    public string $status = '';

    /**
     * Represents the current synchronization status for the component.
     *
     * @var string
     */
    public string $syncStatus = '';

    protected LegalEntity $legalEntity;

    /**
     * Index ACL flags for the Blade view (authorization lives in EmployeeRequestPolicy).
     *
     * @return array{
     *     request_view: bool,
     *     request_write: bool,
     *     request_delete: bool,
     *     employee_view: bool,
     *     employee_write: bool,
     *     employee_deactivate: bool
     * }
     */
    #[Computed]
    public function indexPermissions(): array
    {
        $user = Auth::user();

        return [
            'request_view' => $user->can('viewAny', EmployeeRequest::class),
            'request_write' => $user->can('create', EmployeeRequest::class),
            'request_delete' => $user->can('create', EmployeeRequest::class),
            'employee_view' => false,
            'employee_write' => false,
            'employee_deactivate' => false,
        ];
    }

    #[Computed]
    public function isSync(): bool
    {
        return $this->isSyncProcessing();
    }

    /**
     * Get the synchronization status of the employee request.
     *
     * @return string The current sync status
     */
    protected function getSyncStatus(): string
    {
        return legalEntity()?->getEntityStatus(LegalEntity::ENTITY_EMPLOYEE_REQUEST) ?? '';
    }

    /**
     * Determine if a synchronization process is currently running.
     *
     * @return bool True if a sync process is actively processing, false otherwise.
     */
    protected function isSyncProcessing(): bool
    {
        // Set the sync status only for EmployeeRequest
        $this->syncStatus = $this->getSyncStatus();

        return legalEntity()?->isSyncBlocked(LegalEntity::ENTITY_EMPLOYEE_REQUEST) ?? false;
    }

    public function boot(): void
    {
        $this->legalEntity = legalEntity();

        // This will ensure that the 'isSync' computed property is not cached between requests
        unset($this->isSync);
    }

    public function mount(LegalEntity $legalEntity): void
    {
        $this->authorize('viewAny', EmployeeRequest::class);

        $this->legalEntity = $legalEntity;

        $this->loadDictionaries();

        $this->syncStatus = $this->getSyncStatus();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function syncOne(int $requestId, EmployeeRequestProcessor $processor): void
    {
        Log::info("[SyncOne] Started for Request ID: {$requestId}");

        $localRequest = EmployeeRequest::with(['revision', 'employee', 'party', 'division'])
            ->where('legal_entity_id', legalEntity()->id)
            ->find($requestId);

        if (!$localRequest) {
            session()->flash('error', __('employees.sync.employee_request_not_found'));

            return;
        }

        if (Auth::user()->cannot('viewAny', EmployeeRequest::class)) {
            session()->flash('error', __('employees.sync.employee_request_forbidden'));

            return;
        }

        if (!$localRequest->needsEhealthSync()) {
            session()->flash('warning', __('employees.sync.employee_request_not_pending'));

            return;
        }

        try {
            $result = $processor->syncSinglePendingRequest($localRequest, legalEntity());

            $type = match ($result['outcome']) {
                EmployeeRequestProcessor::OUTCOME_APPROVED => 'success',
                EmployeeRequestProcessor::OUTCOME_PENDING,
                EmployeeRequestProcessor::OUTCOME_REJECTED,
                EmployeeRequestProcessor::OUTCOME_EXPIRED => 'info',
                default => 'error',
            };

            session()->flash($type, $result['message']);

            unset($this->requests);
        } catch (Exception $e) {
            Log::error('[SyncOne] ERROR: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            session()->flash('error', __('employees.sync.employee_request_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Mass synchronization.
     * Process 1st page synchronously, dispatch Job for the rest.
     */
    public function sync(EmployeeRequestProcessor $processor): void
    {
        if (Auth::user()->cannot('viewAny', EmployeeRequest::class)) {
            session()->flash('error', __('employees.sync.employee_request_forbidden'));

            return;
        }

        if ($this->isSyncProcessing()) {
            Session::flash('error', __('forms.errors.sync_already_running'));

            return;
        }

        $user = Auth::user();
        $token = session()->get(config('ehealth.api.oauth.bearer_token'));

        if (!is_string($token) || $token === '') {
            session()->flash('error', __('employees.sync.session_token_missing'));

            return;
        }

        // Try to resume previous sync if it was paused or failed
        if ($this->syncStatus === JobStatus::PAUSED->value || $this->syncStatus === JobStatus::FAILED->value) {

            if ($this->resumeSynchronization($user, $token)) {
                $user->notify(new SyncNotification('employee_request', 'resumed'));

                return;
            }

            // The failed batch could have been restarted by a concurrent request, so don't start a full sync over it
            if ($this->hasRunningBatch([self::BATCH_NAME, self::DEPENDENT_BATCH_NAME, 'Full Employee Requests Sync for LE: ' . legalEntity()->id])) {
                Session::flash('error', __('forms.errors.sync_already_running'));

                return;
            }
        }

        try {
            $response = EHealth::employeeRequest()->getMany([
                'edrpou' => $this->legalEntity->edrpou,
                'status' => RequestStatus::NEW->value,
            ]);
            $processor->processBatch($response->validate(), $this->legalEntity);

            // Even one NEW page must continue with the complete APPROVED and REJECTED lists.
            $job = new EmployeeRequestsSyncAll(
                legalEntity: $this->legalEntity,
                page: $response->isNotLast() ? 2 : 1,
                requestStatus: $response->isNotLast() ? RequestStatus::NEW : RequestStatus::APPROVED,
            );
            $this->legalEntity->setEntityStatus(JobStatus::PROCESSING, LegalEntity::ENTITY_EMPLOYEE_REQUEST);
            Bus::batch([$job])
                ->withOption('legal_entity_id', $this->legalEntity->id)
                ->withOption('token', Crypt::encryptString($token))
                ->withOption('user', $user)
                ->withOption('sync_entity', LegalEntity::ENTITY_EMPLOYEE_REQUEST)
                ->catch(function (Batch $batch, Throwable $e) use ($user): void {
                    Log::error('EmployeeRequest sync batch failed.', ['batch_id' => $batch->id, 'exception' => $e]);
                    $user->notify(new EmployeeRequestSyncCompleted(__('employees.sync.failed'), 'error'));
                })
                ->onQueue('sync')
                ->name(self::BATCH_NAME)
                ->dispatch();

            $user->notify(new SyncNotification('employee_request', 'started'));
            session()->flash('success', __('employees.sync.started'));
            $this->resetPage();
        } catch (Throwable $e) {
            $this->legalEntity->setEntityStatus(JobStatus::FAILED, LegalEntity::ENTITY_EMPLOYEE_REQUEST);
            Log::error('Employee Request sync failed.', ['exception' => $e]);
            session()->flash('error', __('employees.sync.employee_request_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Resume the synchronization process for a user with the provided token.
     *
     * This method handles the continuation of a previously initiated synchronization
     * operation for a specific user using an authentication or session token.
     *
     * @param  User  $user  The user instance for whom synchronization should be resumed
     * @param  string  $token  The authentication or session token used to resume the sync process
     * @return bool True if a failed batch was found and restarted
     */
    protected function resumeSynchronization(User $user, string $token): bool
    {
        $encryptedToken = Crypt::encryptString($token);

        // Define the daily sync batch name (created by EmployeeRequestsSyncAll listener)
        $dailySyncName = 'Full Employee Requests Sync for LE: ' . legalEntity()->id;

        // Find all the EmployeeRequests failed batches for this legal entity and retry them
        $failedBatches = $this->findFailedBatchesByLegalEntity(legalEntity()->id, 'ASC');

        foreach ($failedBatches as $batch) {
            if (\in_array($this->baseBatchName($batch->name), [self::BATCH_NAME, self::DEPENDENT_BATCH_NAME, $dailySyncName], true)) {
                Log::info('Resuming EmployeeRequest sync batch: ' . $batch->name . ' id: ' . $batch->id);

                if ($this->restartBatch(
                    $batch,
                    $user,
                    $encryptedToken,
                    legalEntity(),
                    fn () => legalEntity()?->setEntityStatus(JobStatus::PROCESSING, LegalEntity::ENTITY_EMPLOYEE_REQUEST)
                )) {
                    session()->flash('success', __('forms.success.sync_resumed'));

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Fetches the paginated list of all requests.
     * English annotations used as requested.
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return EmployeeRequest::query()
            ->with(['party', 'division', 'revision'])
            ->where('legal_entity_id', legalEntity()->id)
            ->whereHas('revision')
            ->when($this->search, fn ($query) => $query->searchByFullName($this->search))
            ->when($this->status !== '', function ($query) {
                match ($this->status) {
                    RequestStatus::FILTER_DRAFT => $query->localDraft(),
                    RequestStatus::NEW->value => $query->pendingEhealth(),
                    default => $query->where('status', $this->status),
                };
            })
            ->orderByRaw('COALESCE(inserted_at, created_at) DESC')
            ->paginate(20);
    }

    public function render(): object
    {
        return view('livewire.employee-request.employee-request-index', [
            'requests' => $this->requests,
            'statuses' => RequestStatus::filterChoices(),
            'dictionaries' => $this->dictionaries,
        ]);
    }
}
