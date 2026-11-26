<?php

declare(strict_types=1);

namespace App\Livewire\Employee;

use AllowDynamicProperties;
use App\Classes\eHealth\EHealth;
use App\Enums\JobStatus;
use App\Enums\Status;
use App\Enums\User\Role;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Jobs\EmployeeSync;
use App\Livewire\Actions\Logout;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\Role as ModelsRole;
use App\Models\User;
use App\Notifications\EmployeeSyncCompleted;
use App\Notifications\SyncNotification;
use App\Repositories\Repository;
use App\Services\Party\PartyVerificationCache;
use App\Models\Relations\Party;
use App\Traits\BatchLegalEntityQueries;
use App\Livewire\Employee\Concerns\DeletesEmployeeRequestDraft;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Exception;
use JsonException;
use UnitEnum;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

#[AllowDynamicProperties]
class EmployeeIndex extends EmployeeComponent
{
    use WithPagination;
    use BatchLegalEntityQueries;
    use DeletesEmployeeRequestDraft;

    protected const string BATCH_NAME = 'EmployeeFullSync';
    protected const string DEPENDENT_BATCH_NAME = 'EmployeeDetailsSync';

    // --- Component State for Filters ---
    public string $search = '';
    public array $status = [
        Status::APPROVED->value,
        Status::REORGANIZED->value,
    ];
    public array $filter = [
        'phone' => '',
        'email' => '',
        'role' => '',
        'position' => '',
        'division_id' => '',
        'tax_id' => '',
        'verification_status' => '',
    ];

    // --- State for Modals ---
    public ?int $employeeToDismissId = null;
    public ?string $employeeToDismissName = null;

    public ?string $batchId = null;
    public string $dismissalMessageType = 'default';

    public int $refreshTrigger = 0;

    private LegalEntity $legalEntity;

    /**
     * Represents the current synchronization status for the component.
     *
     * @var string
     */
    public string $syncStatus = '';

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
        return legalEntity()?->getEntityStatus(LegalEntity::ENTITY_EMPLOYEE) ?? '';
    }

    /**
     * Determine if a synchronization process is currently running.
     *
     * @return bool True if a sync process is actively processing, false otherwise.
     */
    protected function isSyncProcessing(): bool
    {
        // Set the sync status only for Employee
        $this->syncStatus = $this->getSyncStatus();

        return legalEntity()?->isSyncBlocked(
            LegalEntity::ENTITY_EMPLOYEE,
            [LegalEntity::ENTITY_DIVISION]
        ) ?? false;
    }

    public function boot(): void
    {
        $this->legalEntity = legalEntity();

        // This will ensure that the 'isSync' computed property is not cached between requests
        unset($this->isSync);
    }

    public function mount(LegalEntity $legalEntity): void
    {
        $this->authorize('viewAny', Employee::class);

        $this->legalEntity = $legalEntity;

        $this->loadDivisions($legalEntity);

        $this->loadDictionaries();

        // Set the sync status for Employee
        $this->syncStatus = $this->getSyncStatus();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, array{verification_status: mixed}>
     */
    public function partyVerificationDetails(Party $party): array
    {
        if (empty($party->uuid)) {
            return [];
        }

        return PartyVerificationCache::get($party->uuid)['details'] ?? [];
    }

    /**
     * Main computed property to fetch and filter parties.
     */
    #[Computed]
    public function parties(): LengthAwarePaginator
    {
        // 1. We get the basic query from the repository (all the complex SQL is hidden there)
        $query = Repository::employee()->getPartiesWithLatestActivityQuery($this->legalEntity->id);

        // 2. Apply dynamic filters (Search, Email, Phone, etc.)
        // This method (applyDatabaseFilters) remains in the component because it is responsible for UI filtering
        $this->applyDatabaseFilters($query);

        // 3. Return the paginated result
        return $query->paginate(10);
    }

    /**
     * Applies UI filters (Search, Email, Phone, Status) to the query builder.
     */
    private function applyDatabaseFilters(Builder $query): void
    {
        // Only parties with real Employee records for this legal entity (requests live on EmployeeRequestIndex).
        $query->whereHas('employees', function ($sub) {
            $sub->where('legal_entity_id', $this->legalEntity->id);
            $this->applyChildFilters($sub);
        });

        // 2. Filter: Search Text (Full Name, Case-Insensitive, Order-Independent)
        // Each word must match any of last/first/second name — "Іван Петренко" and "Петренко Іван" both work.
        if (!empty($this->search)) {
            $words = preg_split('/\s+/u', trim($this->search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($words as $word) {
                $searchTerm = '%' . $word . '%';
                $query->where(function (Builder $q) use ($searchTerm) {
                    $q->where('last_name', 'ILIKE', $searchTerm)
                        ->orWhere('first_name', 'ILIKE', $searchTerm)
                        ->orWhere('second_name', 'ILIKE', $searchTerm);
                });
            }
        }

        // 3. Filter: Email (via Users on Party)
        if (!empty($this->filter['email'])) {
            $query->whereHas(
                'users',
                fn ($q) => $q->where('email', 'ILIKE', '%' . $this->filter['email'] . '%')
            );
        }

        // 4. Filter: Phone
        if (!empty($this->filter['phone'])) {
            $query->whereHas('phones', fn ($q) => $q->where('number', 'like', '%' . $this->filter['phone'] . '%'));
        }

        // 5–6. tax_id / verification_status — 3.23.3.1.1 (OWNER/HR/ADMIN/PHARMACY_OWNER only)
        if ($this->canViewPartyVerificationMeta()) {
            if (!empty($this->filter['tax_id'])) {
                $query->where('tax_id', 'ILIKE', '%' . trim($this->filter['tax_id']) . '%');
            }

            if (!empty($this->filter['verification_status'])) {
                $query->where('verification_status', $this->filter['verification_status']);
            }
        }
    }

    /**
     * Party tax_id / verification_status in list UI — TZ 3.23.3.1 (elevated roles only).
     */
    private function canViewPartyVerificationMeta(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasAllowedRole([Role::ADMIN, Role::HR, Role::OWNER, Role::PHARMACY_OWNER]);
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        $options = [];
        foreach (Status::cases() as $status) {
            if ($status->isEmploymentStatus()) {
                $options[$status->value] = $status->employmentLabel();
            }
        }

        return $options;
    }

    /** @return list<string> */
    protected function statusesForQuery(): array
    {
        return array_values(array_intersect(array_keys($this->statusFilterOptions()), $this->status));
    }

    /**
     * Positions shown for a party after applying list filters (status, role, position, division).
     * Party-level whereHas only decides which parties appear; this trims rows inside each party card.
     * EmployeeRequest drafts/pending updates are listed only on EmployeeRequestIndex.
     *
     * @return Collection<int, mixed>
     */
    public function positionsForParty(Party $party): Collection
    {
        $legalEntityId = $this->legalEntity->id;
        $allowed = $this->statusesForQuery();

        $positions = $party->employees
            ->where('legal_entity_id', $legalEntityId)
            ->sortByDesc('updated_at');

        return $positions
            ->filter(function ($position) use ($allowed) {
                if ($allowed !== []) {
                    $status = $position->status instanceof UnitEnum
                        ? $position->status->value
                        : (string) $position->status;

                    if (!in_array($status, $allowed, true)) {
                        return false;
                    }
                }

                if (!empty($this->filter['division_id'])
                    && (string) $position->division_id !== (string) $this->filter['division_id']
                ) {
                    return false;
                }

                $employeeType = $position->employeeType ?? $position->employee_type ?? null;
                if (!empty($this->filter['role']) && (string) $employeeType !== (string) $this->filter['role']) {
                    return false;
                }

                $positionCode = $position->position ?? null;
                if (!empty($this->filter['position']) && (string) $positionCode !== (string) $this->filter['position']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Helper to apply role, division, position, and status filters to relationship subqueries.
     */
    private function applyChildFilters(Builder $subQuery): void
    {
        $dbStatuses = $this->statusesForQuery();
        if ($dbStatuses !== []) {
            $subQuery->whereIn('status', $dbStatuses);
        }

        // Division Filter
        if (!empty($this->filter['division_id'])) {
            $subQuery->where('division_id', $this->filter['division_id']);
        }

        // Role Filter
        if (!empty($this->filter['role'])) {
            $subQuery->where('employee_type', $this->filter['role']);
        }

        // Position Filter
        if (!empty($this->filter['position'])) {
            $subQuery->where('position', $this->filter['position']);
        }
    }

    public function showModalDeactivate(int $id): void
    {
        $this->openDeactivationModal(Employee::find($id));
    }

    public function resetFilters(): void
    {
        $this->reset(['filter', 'status', 'search']);
        $this->resetPage();
    }

    public function deactivate()
    {
        $employee = $this->deactivateEmployment();
        if (!$employee) {
            return;
        }

        $this->deactivateEmployeeRole($employee);

        $sessionUser = Auth::guard('ehealth')->user() ?? Auth::guard('web')->user();
        if ($sessionUser instanceof User && $employee->partyId !== null && (int) $employee->partyId === (int) $sessionUser->partyId) {
            return app(Logout::class)(message: __('employees.dismissalSuccess'));
        }

        Session::flash('success', __('employees.dismissalSuccess'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws Throwable
     * @throws NotFoundExceptionInterface
     */
    public function sync(): void
    {
        $user = Auth::user();
        if ($user->cannot('sync', Employee::class)) {
            Session::flash('error', __('employees.policy.emp.update_denied'));

            return;
        }

        if ($this->isSyncProcessing()) {
            Session::flash('error', __('forms.errors.sync_already_running'));

            return;
        }

        $token = session()->get(config('ehealth.api.oauth.bearer_token'));

        // Try to resume previous sync if it was paused or failed
        if ($this->syncStatus === JobStatus::PAUSED->value || $this->syncStatus === JobStatus::FAILED->value) {

            if ($this->resumeSynchronization($user, $token)) {
                $user->notify(new SyncNotification('employee', 'resumed'));

                return;
            }

            // The failed batch could have been restarted by a concurrent request, so don't start a full sync over it
            if ($this->hasRunningBatch([self::BATCH_NAME, self::DEPENDENT_BATCH_NAME])) {
                Session::flash('error', __('forms.errors.sync_already_running'));

                return;
            }
        }

        try {
            $response = EHealth::employee()->getMany(['legal_entity_id' => $this->legalEntity->uuid]);
            Repository::employee()->upsertSummaries($response->validate(), $this->legalEntity->id);
        } catch (EHealthConnectionException $e) {
            Log::error('Employee sync failed: No connection to E-Health.', ['error' => $e->getMessage()]);
            Session::flash('error', __('errors.ehealth.messages.no_connection'));

            return;
        } catch (EHealthResponseException $e) {
            Log::error(
                'Employee sync failed: E-Health API error.',
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            Session::flash('error', __('employees.requestError', ['error' => $e->getMessage()]));

            return;
        } catch (Exception $e) {
            Log::error(
                'Employee sync failed: An unexpected error occurred during initiation.',
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            Session::flash('error', __('employees.sync.error'));

            return;
        }

        $user->notify(new SyncNotification('employee', 'started'));
        Session::flash('success', __('employees.sync.started'));

        if ($response->isNotLast()) {
            Bus::batch([
                new EmployeeSync(
                    legalEntity: $this->legalEntity,
                    page: 2,
                    nextEntity: null
                ),
            ])
                ->withOption('legal_entity_id', $this->legalEntity->id)
                ->withOption('token', Crypt::encryptString($token))
                ->withOption('user', $user)
                ->withOption('sync_entity', LegalEntity::ENTITY_EMPLOYEE)
                ->then(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions())
                ->catch(callback: function (Batch $batch, Throwable $e) use ($user) {
                    $message = __('employees.sync.failed');
                    Log::error('Employee sync batch failed.', ['batch_id' => $batch->id, 'exception' => $e]);
                    $user->notify(new EmployeeSyncCompleted($message, 'error'));
                })
                ->onQueue('sync')
                ->name(self::BATCH_NAME)
                ->dispatch();
        } else {
            Bus::batch($this->getEmployeeDetailsStartJob($this->legalEntity, null))
                ->withOption('legal_entity_id', $this->legalEntity->id)
                ->withOption('token', Crypt::encryptString($token))
                ->withOption('user', $user)
                ->withOption('sync_entity', LegalEntity::ENTITY_EMPLOYEE)
                ->catch(callback: function (Batch $batch, Throwable $e) use ($user) {
                    $message = __('employees.sync.failed');
                    Log::error('Employee sync batch failed.', ['batch_id' => $batch->id, 'exception' => $e]);
                    $user->notify(new EmployeeSyncCompleted($message, 'error'));
                })
                ->onQueue('sync')
                ->name(self::DEPENDENT_BATCH_NAME)
                ->dispatch();
        }

        legalEntity()?->setEntityStatus(JobStatus::PROCESSING, LegalEntity::ENTITY_EMPLOYEE);

        $this->dispatch('flashMessage', [
            'message' => "Сторінка 1 оброблена. Решта завантажується фоново.",
            'type' => 'success'
        ]);
    }

    /**
     * Resume the synchronization process for a user with the provided token.
     *
     * This method handles the continuation of a previously initiated synchronization
     * operation for a specific user using an authentication or session token.
     *
     * @param  User  $user  The user instance for whom synchronization should be resumed
     * @param  string  $token  The authentication or session token used to resume the sync process
     * @return void
     */
    protected function resumeSynchronization(User $user, string $token): bool
    {
        $encryptedToken = Crypt::encryptString($token);

        // Find all the EmployeeRequests failed batches for this legal entity and retry them
        $failedBatches = $this->findFailedBatchesByLegalEntity(legalEntity()->id, 'ASC');

        foreach ($failedBatches as $batch) {
            if (\in_array($this->baseBatchName($batch->name), [self::BATCH_NAME, self::DEPENDENT_BATCH_NAME], true)) {
                Log::info('Resuming Employee sync batch: ' . $batch->name . ' id: ' . $batch->id);

                if ($this->restartBatch(
                    $batch,
                    $user,
                    $encryptedToken,
                    legalEntity(),
                    fn () => legalEntity()?->setEntityStatus(JobStatus::PROCESSING, LegalEntity::ENTITY_EMPLOYEE)
                )) {
                    $this->dispatch('flashMessage', [
                        'message' => __('forms.success.sync_resumed'),
                        'type' => 'success'
                    ]);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Synchronize a specific employee.
     * Uses the parent syncEmployeeData method.
     */
    public function syncOne(int $employeeId): void
    {
        $employee = Employee::with(['party'])->find($employeeId);

        if (!$employee) {
            $this->dispatch('flashMessage', ['message' => 'Працівника не знайдено', 'type' => 'error']);

            return;
        }

        // Call the core logic from EmployeeComponent
        $success = $this->syncEmployeeData($employee);

        if ($success) {
            $this->refreshTrigger++;
        }
    }

    /**
     * Revoke the Spatie role of the deactivated employee type for linked users,
     * unless another APPROVED employee of the same type remains in this legal entity.
     * Also drops stale direct eHealth scopes so the next login can resync them.
     */
    protected function deactivateEmployeeRole(Employee $employee): void
    {
        $linkedUsers = $this->usersLinkedToEmployee($employee);

        $employee->users()->detach();

        if ($linkedUsers->isEmpty()) {
            return;
        }

        $guards = array_keys((array) config('auth.guards'));
        $savedGuard = Auth::getDefaultDriver();
        $employeeType = $employee->employeeType;

        setPermissionsTeamId($this->legalEntity->id);

        foreach ($linkedUsers as $user) {
            if (
                is_string($employeeType)
                && $employeeType !== ''
                && !$employee->userHasOtherApprovedOfType((int) $user->id, (int) $this->legalEntity->id)
            ) {
                foreach ($guards as $guard) {
                    Auth::shouldUse($guard);

                    if ($user->hasRole($employeeType, $guard)) {
                        $user->removeRole(ModelsRole::findByName($employeeType, $guard));
                    }
                }
            }

            // Direct eHealth scopes are synced on login only; drop stale rows after deactivate.
            $user->syncPermissions([]);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        Auth::shouldUse($savedGuard);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Users bound to the employee via `user_id` or the `employee_users` pivot.
     *
     * @return Collection<int, User>
     */
    protected function usersLinkedToEmployee(Employee $employee): Collection
    {
        $users = collect();

        if ($employee->userId) {
            $owner = User::query()->without(['person'])->find($employee->userId);

            if ($owner instanceof User) {
                $users->push($owner);
            }
        }

        return $users
            ->concat($employee->users()->without(['person'])->get())
            ->unique('id')
            ->values();
    }

    /**
     * Renders the component view.
     *
     * @throws JsonException
     */
    public function render(): object
    {
        $filterKey = md5(
            $this->search .
            implode(',', $this->status) .
            json_encode($this->filter, JSON_THROW_ON_ERROR) .
            $this->getPage() .
            $this->refreshTrigger
        );

        return view('livewire.employee.employee-index', [
            'parties' => $this->parties,
            'dictionaries' => $this->dictionaries,
            'filterKey' => $filterKey,
        ]);
    }
}
