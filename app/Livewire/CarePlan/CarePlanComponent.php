<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Classes\eHealth\Api\Approval as ApprovalApi;
use App\Classes\eHealth\EHealth;
use App\Core\Arr;
use App\Core\BaseForm;
use App\Enums\CarePlanStatus;
use App\Enums\JobStatus;
use App\Enums\MedicalProgram\Type;
use App\Enums\Person\AuthenticationMethod;
use App\Enums\Person\ServiceRequestStatus;
use App\Enums\User\Role;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthJobTimeoutException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Exceptions\Signature\SignatureException;
use App\Jobs\CarePlanFullSync;
use App\Livewire\CarePlan\Forms\CarePlanActivityForm;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Livewire\Concerns\ReadsClinicalSnapshots;
use App\Livewire\Concerns\SubmitsClinicalOperations;
use App\Livewire\Referral\Forms\ServiceRequestForm;
use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\DeviceRequestRequest;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Repositories\CarePlanActivityRepository;
use App\Repositories\CarePlanRepository;
use App\Repositories\MedicalEvents\Repository as MedicalEventsRepository;
use App\Repositories\Repository;
use App\Services\Dictionary\DictionaryManager;
use App\Services\Dictionary\MedicationProgramPicker;
use App\Services\MedicalEvents\CarePlanActivityEHealthGuard;
use App\Services\MedicalEvents\CarePlanActivityValidationService;
use App\Services\MedicalEvents\CarePlanApprovalService;
use App\Services\MedicalEvents\CarePlanLifecycleGateService;
use App\Services\MedicalEvents\DeviceProgramParticipationGuard;
use App\Services\MedicalEvents\Fhir;
use App\Services\MedicalEvents\FhirResource;
use App\Services\MedicalEvents\Mappers\DeviceRequestMapper;
use App\Services\MedicalEvents\Mappers\ServiceRequestMapper;
use App\Services\MedicalEvents\MedicalRequestOwnership;
use App\Services\MedicalEvents\MedicationRequestLifecycleService;
use App\Services\MedicalEvents\PatientApprovalService;
use App\Services\MedicalEvents\ReferralRequestLifecycleService;
use App\Traits\InteractsWithApprovals;
use App\Traits\SynchronizesCarePlans;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

abstract class CarePlanComponent extends Component
{
    use InteractsWithApprovals;
    use ReadsClinicalSnapshots;
    use SubmitsClinicalOperations;
    use SynchronizesCarePlans;

    // Everything the server derives is #[Locked]: it is sent to the browser on every request
    // and would otherwise come back whatever the client made of it. Only properties bound with
    // wire:model or entangled with Alpine stay writable.

    #[Locked]
    public CarePlan $carePlan;

    #[Locked]
    public array $planSubmission = [];

    #[Locked]
    public ?string $inpatientApprovalStatus = null;

    public bool $showSignatureModal = false;
    public string $actionType = ''; // 'cancel', 'complete', 'sign_activity', 'complete_activity', 'cancel_activity'
    public string $statusReason = ''; // Used when cancelling or completing

    #[Locked]
    public ?int $activityToSign = null;

    #[Locked]
    public array $dictionaries = [];

    /** Entangled with Alpine in the method selection modal, so it cannot be locked. */
    #[Locked]
    public array $authMethods = [];

    public bool $showMethodSelectionModal = false;

    #[Locked]
    public ?string $carePlanUuid = null;

    // Drawer visibility controls (entangled with Alpine)
    public bool $showServiceDrawer = false;
    public bool $showServiceSearchDrawer = false;
    public bool $showMedicationDrawer = false;
    public bool $showMedicationSearchDrawer = false;
    public bool $showMedicationFormDrawer = false;
    public bool $showMedicalDeviceDrawer = false;
    public bool $showMedicalDeviceSearchDrawer = false;
    public bool $showMedicalDeviceFormDrawer = false;

    /** @var list<string> */
    #[Locked]
    public array $participatingDeviceProgramIds = [];

    #[Locked]
    public string $deviceParticipationWarning = '';

    public bool $showEPrescriptionDrawer = false;
    public array $ePrescriptionForm = [];

    #[Locked]
    public ?array $ePrescriptionSelectedActivity = null;

    #[Locked]
    public ?array $ePrescriptionSelectedProduct = null;

    #[Locked]
    public ?array $ePrescriptionSelectedProgram = null;

    #[Locked]
    public float $ePrescriptionRemainingQty = 0.0;

    public bool $ePrescriptionSkipTreatmentPeriod = true;

    #[Locked]
    public bool $ePrescriptionShowDailyDoseWarning = false;

    #[Locked]
    public bool $ePrescriptionShowRemainingQtyWarning = false;

    #[Locked]
    public string $ePrescriptionRemainingQtyWarningMessage = '';

    #[Locked]
    public string $ePrescriptionWarningMessage = '';

    #[Locked]
    public array $ePrescriptionMultiples = [];

    #[Locked]
    public array $ePrescriptionPackages = [];

    #[Locked]
    public array $ePrescriptionAuthMethods = [];

    /** @var list<array{id:int,uuid:string,label:string}> */
    #[Locked]
    public array $ePrescriptionEligibleEncounters = [];

    #[Locked]
    public ?string $ePrescriptionRequestIdToSign = null;

    #[Locked]
    public string $printableContent = '';

    #[Locked]
    public array $activePrescriptions = [];

    // Outgoing Referral State Variables
    public bool $showReferralDrawer = false;
    public array $referralForm = [];

    #[Locked]
    public ?array $referralSelectedActivity = null;

    #[Locked]
    public float $referralRemainingQty = 0.0;

    #[Locked]
    public bool $referralShowRemainingQtyWarning = false;

    #[Locked]
    public string $referralWarningMessage = '';

    #[Locked]
    public ?string $referralRequestIdToSign = null;

    #[Locked]
    public array $activeReferrals = [];

    #[Locked]
    public string $referralServiceCategory = '';

    #[Locked]
    public string $referralServiceType = 'service';

    #[Locked]
    public array $referralCategoryOptions = [];

    /** Package step for device eRx (packaging_count); 0 when unknown / service referral. */
    #[Locked]
    public int $referralDevicePackageQty = 0;

    /** @var list<array{uuid: string, label: string, raw: string}> */
    #[Locked]
    public array $referralAuthMethods = [];

    public string $referralExplanatoryLetter = '';

    // Search and selection parameters
    public string $searchQuery = '';

    #[Locked]
    public array $searchResults = [];

    #[Locked]
    public int $searchPage = 1;

    #[Locked]
    public ?array $selectedProduct = null;

    public string $selectedProgram = '';

    // Linked justification references (grounds)
    #[Locked]
    public array $linkedGrounds = [];

    #[Locked]
    public array $availableReports = [];

    #[Locked]
    public array $availableObservations = [];

    // Activity Form state
    public array $activityForm = [
        'id' => null,
        'kind' => 'service_request',
        'program' => '',
        'quantity' => '',
        'quantity_system' => '',
        'quantity_code' => '',
        'daily_amount' => '',
        'daily_amount_system' => '',
        'daily_amount_code' => '',
        'reason_code' => '',
        'reason_reference' => '',
        'goal' => '',
        'description' => '',
        'scheduled_period_start' => '',
        'scheduled_period_end' => '',
        'product_reference' => '',
        'product_codeable_concept' => '',
    ];

    public BaseForm $form;

    public string $outcomeCode = ''; // For outcomeCodeableConcept

    /** Built by addOutcomeReference()/removeOutcomeReference(), never posted by the browser. */
    #[Locked]
    public array $outcomeReferences = [];

    public ?string $selectedOutcomeReference = null;

    #[Locked]
    public array $availableConditions = [];

    /**
     * @return list<array{uuid: string, type: string, name: string, date: string}>
     */
    public function getSelectedOutcomeReferencesDetailsProperty(): array
    {
        $catalog = collect($this->availableConditions)
            ->map(fn (array $item): array => $item + ['type' => __('Діагноз/Стан')])
            ->concat(collect($this->availableObservations)->map(fn (array $item): array => $item + ['type' => __('Спостереження')]))
            ->concat(collect($this->availableReports)->map(fn (array $item): array => $item + ['type' => __('Діагностичний звіт')]))
            ->keyBy('uuid');

        return collect($this->outcomeReferences)
            ->map(function (string $uuid) use ($catalog): ?array {
                $item = $catalog->get($uuid);
                if (!is_array($item)) {
                    return null;
                }

                return [
                    'uuid' => $uuid,
                    'type' => (string) ($item['type'] ?? ''),
                    'name' => (string) ($item['name'] ?? $uuid),
                    'date' => (string) ($item['date'] ?? '-'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function addOutcomeReference(): void
    {
        if ($this->selectedOutcomeReference === null || $this->selectedOutcomeReference === '') {
            return;
        }

        if (!in_array($this->selectedOutcomeReference, $this->outcomeReferences, true)) {
            $this->outcomeReferences[] = $this->selectedOutcomeReference;
        }

        $this->selectedOutcomeReference = null;
    }

    public function removeOutcomeReference(string $uuid): void
    {
        $this->outcomeReferences = array_values(array_filter(
            $this->outcomeReferences,
            static fn (string $reference): bool => $reference !== $uuid
        ));
    }

    public function hydrate(): void
    {
        if (isset($this->carePlan)) {
            $this->authorize('view', $this->carePlan);
            $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);
            if (property_exists($this, 'activity') && isset($this->activity)) {
                $this->activity = $this->ownedActivity($this->activity->id);
            }
        }
    }

    protected function authorizeCarePlanActivityWrite(): void
    {
        $this->authorizeCarePlanWrite();
        // Use fresh consent state for a mutation, even if the page was opened before it expired.
        $plan = Repository::carePlan()->findById($this->carePlan->id);
        $this->authorize('manageActivities', $plan);
    }

    public function getCanManageActivitiesProperty(): bool
    {
        return !$this->isTerminalCarePlan && !$this->carePlan->requiresFhirImport()
            && Auth::user()?->can('manageActivities', $this->carePlan) === true;
    }

    protected function authorizeCarePlanWrite(): void
    {
        $this->authorize('manage', $this->carePlan);
        if ($this->carePlan->requiresFhirImport()) {
            $message = __($this->carePlan->status === CarePlanStatus::DRAFT->value ? 'care-plan.draft_backfill_required' : 'care-plan.full_sync_required');
            Session::flash('error', $message);
            throw ValidationException::withMessages(['carePlan' => $message]);
        }
    }

    protected function ownedActivity(int $activityId): CarePlanActivity
    {
        return Repository::carePlanActivity()->findOwned($this->carePlan->id, $activityId);
    }

    protected function ownedActivityByBasedOnUuid(?string $activityUuid): CarePlanActivity
    {
        return Repository::carePlanActivity()->findOwnedByUuid($this->carePlan->id, $activityUuid);
    }

    protected function bootCarePlan(CarePlan $carePlan): void
    {
        $this->authorize('view', $carePlan);

        $this->carePlan = Repository::carePlan()->findById($carePlan->id);

        $personId = $this->carePlan->personId;

        $grounds = Repository::carePlan()->availableGrounds($personId);
        $this->availableConditions = $grounds['conditions'];
        $this->availableReports = $grounds['reports'];
        $this->availableObservations = $grounds['observations'];

        // Basic dictionaries and medical programs come from different eHealth endpoints.
        // They are loaded separately so a missing optional dictionary cannot leave the
        // programme dropdowns silently empty.
        try {
            $basics = app(DictionaryManager::class)->basics();
            $this->dictionaries['care_plan_categories'] = $basics->byName('eHealth/care_plan_categories')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_activity_outcomes'] = $basics->byName('eHealth/care_plan_activity_outcomes')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_activity_goals'] = $basics->byName('eHealth/care_plan_activity_goals')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_cancel_reasons'] = $basics->byName('eHealth/care_plan_cancel_reasons')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_complete_reasons'] = $basics->byName('eHealth/care_plan_complete_reasons')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_activity_complete_reasons'] = $basics->byName('eHealth/care_plan_activity_complete_reasons')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['care_plan_activity_cancel_reasons'] = $basics->byName('eHealth/care_plan_activity_cancel_reasons')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['MEDICATION_REQUEST_REJECT_REASON'] = $basics->byName('MEDICATION_REQUEST_REJECT_REASON')
                ?->asCodeDescription()
                ?->toArray()
                ?? $basics->byName('eHealth/MEDICATION_REQUEST_REJECT_REASON')
                    ?->asCodeDescription()
                    ?->toArray()
                ?? [];

            $this->dictionaries['device_definition_classification_type'] = $basics->byName('device_definition_classification_type')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['eHealth/assistive_devices'] = $basics->byName('eHealth/assistive_devices')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['device_definition_packaging_type'] = $basics->byName('device_definition_packaging_type')
                ?->asCodeDescription()
                ?->toArray() ?? [];

            $this->dictionaries['device_unit'] = $basics->byName('device_unit')
                ?->asCodeDescription()
                ?->toArray() ?? [];
        } catch (Exception $exception) {
            Log::warning('CarePlanShow: failed to load basic dictionaries: ' . $exception->getMessage());
        }

        try {
            // Medical programs, split by type; device/medication lists are role-filtered.
            $programs = app(DictionaryManager::class)->medicalPrograms();
            $this->dictionaries['medical_programs'] = $programs
                ->pluck('name', 'id')
                ->toArray() ?? [];

            $this->dictionaries['medical_programs_medication'] = $this->filterMedicationPrograms(
                $programs->filter(fn ($program) => strtoupper($program['type'] ?? '') === Type::MEDICATION->value)
            )->pluck('name', 'id')->toArray() ?? [];

            $this->dictionaries['medical_programs_device'] = $this->filterDevicePrograms(
                $programs->filter(fn ($program) => strtoupper($program['type'] ?? '') === Type::DEVICE->value)
            )->pluck('name', 'id')->toArray() ?? [];

            $this->dictionaries['medical_programs_service'] = $this->filterServicePrograms(
                $programs->filter(fn ($program) => strtoupper($program['type'] ?? '') === Type::SERVICE->value)
            )->pluck('name', 'id')->toArray() ?? [];
        } catch (Exception $exception) {
            Log::warning('CarePlanShow: failed to load medical programs: ' . $exception->getMessage());
        }

        $this->carePlanUuid = $this->carePlan->uuid;
        $this->patientId = $this->carePlan->person->uuid;
        $this->loadDeviceProgramParticipationState();

        $this->activePrescriptions = Repository::carePlanActivity()
            ->linkedDocuments($this->carePlan->activities->pluck('uuid')->all(), 'medication_request', forDisplay: true)
            ->map(function ($record): array {
                $row = $record->toArray();
                $row['based_on_uuid'] = $record->basedOn?->value;

                return $row;
            })
            ->all();
        $this->loadActiveReferrals();

        $action = request()->query('action');
        if (in_array($action, ['cancel', 'complete'], true) && $this->canChangePlanLifecycle) {
            $this->openSignatureModal($action);
        }
    }

    /**
     * Unsigned local draft that still needs a KEP before it exists in eHealth.
     */
    public function getCanSignPlanProperty(): bool
    {
        return $this->carePlanStatusCode() === CarePlanStatus::DRAFT->value
            && !$this->carePlan->requiresFhirImport()
            && !in_array($this->planSubmission['status'] ?? null, [JobStatus::PENDING->value, JobStatus::PROCESSING->value, JobStatus::PROCESSED->value], true)
            && !Repository::carePlan()->hasPendingSubmission($this->carePlan);
    }

    /**
     * Signed in eHealth, still `new`, and the current doctor has no confirmed approval yet.
     */
    public function getCanRequestPatientApprovalProperty(): bool
    {
        return filled($this->carePlan->uuid)
            && !$this->isTerminalCarePlan
            && !$this->isInpatientPlanOfCurrentOrganization
            && !$this->carePlan->requiresFhirImport()
            && $this->carePlanStatusCode() === CarePlanStatus::PENDING->value
            && !in_array($this->planSubmission['status'] ?? null, [JobStatus::PENDING->value, JobStatus::PROCESSING->value], true)
            && !$this->hasGrantedApprovalForCurrentDoctor
            && !Repository::carePlan()->hasPendingSubmission($this->carePlan);
    }

    /**
     * Status actions require a complete registered plan and a current write approval.
     */
    public function getCanChangePlanLifecycleProperty(): bool
    {
        if (!filled($this->carePlan->uuid) || $this->isTerminalCarePlan || $this->carePlan->requiresFhirImport()
            || !Auth::user()?->can('manage', $this->carePlan)
            || Repository::carePlan()->hasPendingSubmission($this->carePlan)) {
            return false;
        }

        return in_array($this->carePlanStatusCode(), [CarePlanStatus::PENDING->value, CarePlanStatus::ACTIVE->value], true)
            && $this->hasGrantedApprovalForCurrentDoctor;
    }

    public function getCanCancelPlanProperty(): bool
    {
        return $this->canChangePlanLifecycle
            && $this->isCurrentDoctorPlanAuthor()
            && app(CarePlanLifecycleGateService::class)->planCancelBlockReason($this->carePlan) === null;
    }

    public function getCanCompletePlanProperty(): bool
    {
        return $this->canChangePlanLifecycle
            && app(CarePlanLifecycleGateService::class)->planCompleteBlockReason($this->carePlan) === null;
    }

    protected function isCurrentDoctorPlanAuthor(): bool
    {
        $authorUuid = $this->carePlan->authorReference?->value ?? $this->carePlan->author?->uuid;

        return filled($authorUuid) && $authorUuid === $this->currentCarePlanEmployee()?->uuid;
    }

    protected function guardPlanLifecycleAccess(string $action): bool
    {
        if ($this->canChangePlanLifecycle && ($action !== 'cancel' || $this->isCurrentDoctorPlanAuthor())) {
            return false;
        }
        Session::flash('error', __('care-plan.lifecycle_access_required'));
        $this->showSignatureModal = false;

        return true;
    }

    public function getIsInpatientPlanOfCurrentOrganizationProperty(): bool
    {
        return app(CarePlanApprovalService::class)->skipsPatientOtp($this->carePlan);
    }

    public function getCanRequestInpatientAccessProperty(): bool
    {
        return $this->isInpatientPlanOfCurrentOrganization
            && !$this->isTerminalCarePlan
            && in_array($this->carePlanStatusCode(), [CarePlanStatus::PENDING->value, CarePlanStatus::ACTIVE->value], true)
            && !$this->carePlan->requiresFhirImport()
            && !$this->hasGrantedApprovalForCurrentDoctor
            && !in_array($this->inpatientApprovalStatus, ['queued', 'pending'], true)
            && !Repository::carePlan()->hasPendingSubmission($this->carePlan);
    }

    public function getHasGrantedApprovalForCurrentDoctorProperty(): bool
    {
        return $this->carePlan->hasGrantedApprovalForEmployeeUuid(
            $this->currentCarePlanEmployee()?->uuid
        );
    }

    #[On('care-plan-approvals-changed')]
    public function onCarePlanApprovalsChanged(): void
    {
        $this->refreshCarePlan();
    }

    protected function rulesForSigning(): array
    {
        $statusReasonOptional = in_array($this->actionType, [
            'sign_activity',
            'sign_plan',
            'sign_eprescription',
            'sign_servicerequest',
            'sign_devicerequest',
            'recall_referral',
        ], true);

        $rules = [
            'statusReason' => $statusReasonOptional ? 'nullable|string' : 'required|string',
        ];

        if ($this->actionType === 'recall_referral') {
            $rules['referralExplanatoryLetter'] = 'required|string|min:3';
        }

        return $rules;
    }

    /**
     * Care Plan and Activity completion use unsigned action bodies.
     */
    protected function requiresDigitalSignature(): bool
    {
        return !in_array($this->actionType, ['complete', 'complete_activity'], true);
    }

    public function getStatusReasonsProperty(): array
    {
        if (in_array($this->actionType, ['cancel_referral', 'recall_referral'], true)) {
            $operation = $this->actionType === 'recall_referral' ? 'recall' : 'cancel';

            return $this->dictionaries['service_request_'.$operation.'_reasons'] ?? [];
        }
        if ($this->actionType === 'complete') {
            return $this->dictionaries['care_plan_complete_reasons'] ?? [];
        }
        if ($this->actionType === 'complete_activity') {
            return $this->dictionaries['care_plan_activity_complete_reasons'] ?? [];
        }
        if ($this->actionType === 'cancel_activity') {
            return $this->dictionaries['care_plan_activity_cancel_reasons'] ?? [];
        }
        if (in_array($this->actionType, ['reject_prescription', 'cancel_prescription'], true)) {
            return $this->dictionaries['MEDICATION_REQUEST_REJECT_REASON'] ?? [];
        }

        return $this->dictionaries['care_plan_cancel_reasons'] ?? [];
    }

    public function updatedSelectedProgram(): void
    {
        $this->activityForm['program'] = $this->selectedProgram;

        if (method_exists($this, 'refreshDeviceSelectionWarning')) {
            $this->refreshDeviceSelectionWarning();
        }
    }

    public function openSignatureModal(string $actionType, ?int $activityId = null, ?string $requestUuid = null): void
    {
        $this->authorizeCarePlanWrite();
        if (in_array($actionType, ['sign_activity', 'cancel_activity', 'complete_activity'], true)) {
            $this->authorizeCarePlanActivityWrite();
        }
        if ($this->guardTerminalCarePlanMutation()) {
            return;
        }

        if (in_array($actionType, ['cancel', 'complete'], true) && $this->guardPlanLifecycleAccess($actionType)) {
            return;
        }

        if ($actionType === 'sign_plan') {
            try {
                Repository::carePlan()->assertDraftSigningReady($this->carePlan);
            } catch (ValidationException $exception) {
                Session::flash('error', $exception->validator->errors()->first());
                $this->showSignatureModal = false;

                return;
            }
        }

        try {
            $activity = $activityId ? $this->ownedActivity($activityId) : null;
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        if (in_array($actionType, ['cancel_activity', 'complete_activity'], true) && $activityId) {

            if ($activity) {
                $blockReason = app(CarePlanLifecycleGateService::class)
                    ->activityStatusChangeBlockReason($activity, $actionType);

                if ($blockReason !== null) {
                    Session::flash('error', $blockReason);

                    return;
                }
            }
        }

        if ($actionType === 'cancel') {
            $blockReason = app(CarePlanLifecycleGateService::class)
                ->planCancelBlockReason($this->carePlan);

            if ($blockReason !== null) {
                Session::flash('error', $blockReason);

                return;
            }
        }

        if ($actionType === 'complete') {
            $blockReason = app(CarePlanLifecycleGateService::class)
                ->planCompleteBlockReason($this->carePlan);

            if ($blockReason !== null) {
                Session::flash('error', $blockReason);

                return;
            }
        }

        if (in_array($actionType, ['cancel_referral', 'recall_referral'], true)) {
            $operation = $actionType === 'recall_referral' ? 'recall' : 'cancel';
            try {
                $this->dictionaries['service_request_'.$operation.'_reasons'] = dictionary()->basics()->byName('eHealth/service_request_'.$operation.'_reasons')->asCodeDescription()->all();
            } catch (\Throwable $exception) {
                Log::warning('Referral status reasons could not be loaded', ['exceptionType' => $exception::class]);
                Session::flash('error', __('Не вдалося завантажити довідник причин зміни направлення.'));
                $this->showSignatureModal = false;

                return;
            }
        }
        $this->actionType = $actionType;
        $this->activityToSign = $activityId;
        if ($requestUuid !== null && $requestUuid !== '') {
            if (in_array($actionType, ['cancel_prescription', 'sign_eprescription', 'reject_prescription'], true)) {
                $this->ePrescriptionRequestIdToSign = $requestUuid;
            } elseif (in_array($actionType, ['cancel_referral', 'recall_referral', 'sign_servicerequest', 'sign_devicerequest'], true)) {
                $this->referralRequestIdToSign = $requestUuid;
            }
        }
        $this->statusReason = ''; // Reset reason
        if ($actionType !== 'recall_referral') {
            $this->referralExplanatoryLetter = '';
        }
        $this->outcomeCode = ''; // Reset outcome
        $this->outcomeReferences = []; // Reset references
        $this->showSignatureModal = true;
    }

    protected function currentCarePlanEmployee(): ?Employee
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $termsOfService = $this->carePlan->termsOfServiceCode();

        return $user->getCarePlanWriterEmployee(is_string($termsOfService) ? $termsOfService : null)
            ?? $user->activeDoctorEmployee();
    }

    protected function carePlanStatusCode(): string
    {
        return CarePlanStatus::normalize($this->carePlan->status);
    }

    public function getIsTerminalCarePlanProperty(): bool
    {
        return CarePlanStatus::fromStored($this->carePlan->status)->isTerminal();
    }

    protected function guardTerminalCarePlanMutation(): bool
    {
        if (!$this->isTerminalCarePlan) {
            return false;
        }

        Session::flash('error', __('care-plan.cannot_mutate_terminal_care_plan', [
            'status' => CarePlanStatus::labelFor($this->carePlan->status),
        ]));

        return true;
    }

    protected function refreshCarePlan(): void
    {
        $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);
        unset($this->canManageActivities, $this->hasGrantedApprovalForCurrentDoctor, $this->canRequestPatientApproval, $this->canRequestInpatientAccess, $this->canChangePlanLifecycle, $this->canCancelPlan, $this->canCompletePlan);
        $repository = Repository::carePlanActivity();
        if (property_exists($this, 'activity') && $this->activity instanceof CarePlanActivity) {
            $this->activity = $repository->findOwned($this->carePlan->id, $this->activity->id);
        }
        $this->activePrescriptions = $repository->linkedDocuments($this->carePlan->activities->pluck('uuid')->all(), 'medication_request', forDisplay: true)
            ->map(function ($record): array {
                $row = $record->toArray();
                $row['based_on_uuid'] = $record->basedOn?->value;

                return $row;
            })->all();
        $this->loadActiveReferrals();
    }

    protected function loadActiveReferrals(): void
    {
        $activityUuids = $this->carePlan->activities->pluck('uuid')->all();
        $repository = Repository::carePlanActivity();
        $this->activeReferrals = [];
        foreach (['service_request', 'device_request'] as $kind) {
            foreach ($repository->linkedDocuments($activityUuids, $kind) as $record) {
                $this->activeReferrals[] = $this->normalizeReferralForView($record, $kind);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeReferralForView(Model $record, string $kind): array
    {
        $category = $record->category?->text;
        $priority = $record->priority?->text;

        return [
            'uuid' => $record->getAttribute('uuid'),
            'kind' => $kind,
            'based_on_uuid' => $record->basedOn?->value,
            'status' => $record->getAttribute('status'),
            'status_label' => $this->resolveReferralStatusLabel((string) $record->getAttribute('status')),
            'request_number' => $record->getAttribute('request_number'),
            'quantity' => $record->getAttribute('quantity'),
            'started_at' => $kind === 'service_request' ? $record->getRawOriginal('started_at') : $record->getAttribute('started_at'),
            'ended_at' => $kind === 'service_request' ? $record->getRawOriginal('ended_at') : $record->getAttribute('ended_at'),
            'service_id' => $kind === 'service_request' ? $record->getAttribute('service_id') : null,
            'device_id' => $kind === 'device_request' ? $record->getAttribute('device_id') : null,
            'product_code' => $kind === 'service_request'
                ? $record->getAttribute('service_id')
                : $record->getAttribute('device_id'),
            'category' => $category,
            'category_label' => $this->referralCategoryLabel($category),
            'priority' => $priority,
            'priority_label' => $this->referralPriorityLabel($priority),
            'note' => $record->getAttribute('note'),
            'program_id' => $record->getAttribute('program_id'),
            'employee_name' => $record->employee?->full_name ?? null,
        ];
    }

    protected function resolveReferralStatusLabel(string $status): string
    {
        return ServiceRequestStatus::labelFor($status);
    }

    protected function referralCategoryLabel(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        $key = 'care-plan.referral_category.' . $category;

        return Lang::has($key) ? __($key) : $category;
    }

    protected function referralPriorityLabel(?string $priority): ?string
    {
        if ($priority === null || $priority === '') {
            return null;
        }

        $key = 'care-plan.referral_priority.' . $priority;

        return Lang::has($key) ? __($key) : $priority;
    }

    protected function scopeDocumentsToActivity(string $activityUuid): void
    {
        $this->activePrescriptions = array_values(array_filter(
            $this->activePrescriptions,
            static fn (array $prescription): bool => ($prescription['based_on_uuid'] ?? null) === $activityUuid
        ));

        $this->activeReferrals = array_values(array_filter(
            $this->activeReferrals,
            static fn (array $referral): bool => ($referral['based_on_uuid'] ?? null) === $activityUuid
        ));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterDevicePrograms(Collection $programs): Collection
    {
        $user = Auth::user();
        $filtered = $programs->where('is_active', '=', true);

        if ($user) {
            $roles = $user->allowedRoles;
            $mainSpeciality = $user->getMainSpeciality(legalEntity());

            $filtered = $filtered->filter(function (array $program) use ($roles, $user, $mainSpeciality): bool {
                $allowedEmployeeTypes = Arr::get($program, 'medical_program_settings.employee_types_to_create_request', []);
                if (!empty($allowedEmployeeTypes) && $roles->intersect($allowedEmployeeTypes)->isEmpty()) {
                    return false;
                }

                if ($user->hasAllowedRole(Role::SPECIALIST->value) || $user->hasAllowedRole(Role::DOCTOR->value)) {
                    $allowedSpecialities = Arr::get($program, 'medical_program_settings.speciality_types_care_plan_activity_allowed')
                        ?? Arr::get($program, 'medical_program_settings.speciality_types_request_allowed')
                        ?? Arr::get($program, 'medical_program_settings.speciality_types_allowed', []);

                    if (!empty($allowedSpecialities)) {
                        return $mainSpeciality->intersect($allowedSpecialities)->isNotEmpty();
                    }
                }

                return true;
            });
        }

        $activityValidation = app(CarePlanActivityValidationService::class);
        $filtered = $filtered->filter(
            fn (array $program): bool => $activityValidation->providingConditionsBlockReason($this->carePlan, $program) === null
        );

        if ($this->participatingDeviceProgramIds !== []) {
            $filtered = app(DeviceProgramParticipationGuard::class)
                ->filterProgramsForParticipation($filtered, $this->participatingDeviceProgramIds);
        }

        return $filtered;
    }

    protected function loadDeviceProgramParticipationState(): void
    {
        $guard = app(DeviceProgramParticipationGuard::class);
        $this->participatingDeviceProgramIds = $guard->resolveParticipatingProgramIds(legalEntity());
        $this->deviceParticipationWarning = $this->participatingDeviceProgramIds === []
            ? __('care-plan.device_program_participation_sync_hint')
            : '';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterMedicationPrograms(Collection $programs): Collection
    {
        return MedicationProgramPicker::forCurrentUser($programs);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $programs
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterServicePrograms(Collection $programs): Collection
    {
        $user = Auth::user();
        if (!$user) {
            return $programs->where('is_active', '=', true);
        }

        $roles = $user->allowedRoles;
        $mainSpeciality = $user->getMainSpeciality(legalEntity());

        return $programs
            ->where('is_active', '=', true)
            ->filter(function (array $program) use ($roles, $user, $mainSpeciality): bool {
                $allowedEmployeeTypes = Arr::get($program, 'medical_program_settings.employee_types_to_create_request', []);
                if (!empty($allowedEmployeeTypes) && $roles->intersect($allowedEmployeeTypes)->isEmpty()) {
                    return false;
                }

                if ($user->hasAllowedRole(Role::SPECIALIST->value) || $user->hasAllowedRole(Role::DOCTOR->value)) {
                    $allowedSpecialities = Arr::get($program, 'medical_program_settings.speciality_types_care_plan_activity_allowed')
                        ?? Arr::get($program, 'medical_program_settings.speciality_types_request_allowed')
                        ?? Arr::get($program, 'medical_program_settings.speciality_types_allowed', []);

                    if (!empty($allowedSpecialities)) {
                        return $mainSpeciality->intersect($allowedSpecialities)->isNotEmpty();
                    }
                }

                return true;
            });
    }

    public function render()
    {
        return $this->renderCarePlan();
    }

    abstract protected function renderCarePlan();

    #[Locked]
    public bool $activateAfterSubmission = false;

    public bool $showActivationPrompt = false;

    protected function continuePlanActivation(): void
    {
        if (!$this->activateAfterSubmission || !$this->canRequestPatientApproval || Session::get($this->activationPromptSessionKey(), false)) {
            return;
        }
        $this->activateAfterSubmission = false;
        $this->showActivationPrompt = true;
    }

    public function acceptPlanActivation(): void
    {
        $this->authorizeCarePlanWrite();
        Session::put($this->activationPromptSessionKey(), true);
        $this->showActivationPrompt = false;
        $this->openMethodSelectionModal();
    }

    public function dismissPlanActivation(): void
    {
        Session::put($this->activationPromptSessionKey(), true);
        $this->showActivationPrompt = false;
    }

    protected function activationPromptSessionKey(): string
    {
        return 'care-plan.activation-prompt.'.$this->carePlan->id.'.'.($this->planSubmission['id'] ?? 'imported');
    }

    public function refreshDisplayedDetails(): void
    {
        $this->remotePlanDetails = [];

        if (!Auth::user()->can('view', $this->carePlan)) {
            return;
        }

        if (!$this->carePlan->uuid || in_array(CarePlanStatus::fromStored($this->carePlan->status), [CarePlanStatus::PENDING, CarePlanStatus::DRAFT], true)) {
            return;
        }

        try {
            $this->remotePlanDetails = Repository::carePlan()->displayedDetails($this->carePlan, $this->dictionaries ?? []);
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('error', __('device-requests.messages.plan_details_unavailable'));
        }
    }

    public function sign(
        CarePlanRepository $repository,
        CarePlanActivityRepository $activityRepository,
        string $keyContainer = '',
        string $password = ''
    ): void {
        $this->form->useSigningSecrets($keyContainer, $password);
        $this->authorizeCarePlanWrite();
        if (in_array($this->actionType, ['cancel', 'complete'], true) && $this->guardPlanLifecycleAccess($this->actionType)) {
            return;
        }
        $signingData = [];
        try {
            $validated = $this->validate($this->rulesForSigning());

            if ($this->requiresDigitalSignature()) {
                $signingData = $this->form->validate($this->form->signingRules());
            }
        } catch (ValidationException $exception) {
            Session::flash('error', $exception->validator->errors()->first());
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        if ($this->actionType === 'sign_activity') {
            $this->signActivity($repository, $activityRepository, $signingData);

            return;
        }

        if ($this->actionType === 'complete') {
            $this->completePlan($repository);

            return;
        }

        if ($this->actionType === 'sign_eprescription') {
            if (!method_exists($this, 'signEPrescription')) {
                Session::flash('error', __('care-plan.unexpected_error'));
                $this->showSignatureModal = false;

                return;
            }

            $this->signEPrescription($signingData);

            return;
        }

        if ($this->actionType === 'sign_servicerequest' || $this->actionType === 'sign_devicerequest') {
            if (!method_exists($this, 'signReferral')) {
                Session::flash('error', __('care-plan.unexpected_error'));
                $this->showSignatureModal = false;

                return;
            }

            $this->signReferral($signingData);

            return;
        }

        if (in_array($this->actionType, ['complete_activity', 'cancel_activity'])) {
            $this->signStatusActivity($activityRepository, $signingData);

            return;
        }

        if ($this->actionType === 'cancel_prescription' || $this->actionType === 'reject_prescription') {
            if (!method_exists($this, 'signRejectPrescription')) {
                Session::flash('error', __('care-plan.unexpected_error'));
                $this->showSignatureModal = false;

                return;
            }

            // eHealth medication-request flow in this app uses reject (no separate cancel signer).
            $this->signRejectPrescription($signingData);

            return;
        }

        if ($this->actionType === 'cancel_referral') {
            if (!method_exists($this, 'signCancelReferral')) {
                Session::flash('error', __('care-plan.unexpected_error'));
                $this->showSignatureModal = false;

                return;
            }

            $this->signCancelReferral($signingData);

            return;
        }

        if ($this->actionType === 'recall_referral') {
            if (!method_exists($this, 'signRecallReferral')) {
                Session::flash('error', __('care-plan.unexpected_error'));
                $this->showSignatureModal = false;

                return;
            }

            $this->signRecallReferral($signingData);

            return;
        }

        if ($this->actionType === 'sign_plan') {
            if ($this->carePlan->status !== CarePlanStatus::DRAFT->value) {
                $this->showSignatureModal = false;

                return;
            }
            $this->signPlan($repository, $signingData);

            return;
        }

        if (empty($this->carePlan->uuid) || $this->carePlan->status === CarePlanStatus::DRAFT->value) {
            Session::flash('error', __('care-plan.care_plan_not_synced'));
            $this->showSignatureModal = false;

            return;
        }

        if (Auth::user()?->can('cancel', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        if ($this->actionType !== 'cancel') {
            $this->showSignatureModal = false;

            return;
        }

        if ($this->actionType === 'cancel') {
            $planCancelBlock = app(CarePlanLifecycleGateService::class)
                ->planCancelBlockReason($this->carePlan);

            if ($planCancelBlock !== null) {
                Session::flash('error', $planCancelBlock);
                $this->showSignatureModal = false;

                return;
            }
        }

        $systemMap = [
            'cancel' => 'eHealth/care_plan_cancel_reasons',
            'complete' => 'eHealth/care_plan_complete_reasons',
        ];

        $statusReasonCodeableConcept = [
            'coding' => [
                [
                    'system' => $systemMap[$this->actionType] ?? 'eHealth/care_plan_cancel_reasons',
                    'code' => $this->statusReason,
                ]
            ]
        ];

        try {
            $payloadForSign = $this->buildCarePlanStatusChangePayload($statusReasonCodeableConcept);

            Log::info('CarePlanShow: signing care-plan status change', [
                'actionType' => $this->actionType,
                'carePlanId' => $this->carePlan->id,
            ]);

            $signedContent = signatureService()->signData(
                $payloadForSign,
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                Auth::user()->party->taxId
            );

            $finalResponse = $this->submitClinicalOperation($this->carePlan, ['status' => CarePlanStatus::CANCELLED->value, 'statusReason' => $statusReasonCodeableConcept], fn () => EHealth::carePlan()->cancel(
                $this->carePlan->person->uuid,
                $this->carePlan->uuid,
                [
                    'signed_data' => $signedContent,
                    'signed_data_encoding' => 'base64',
                ]
            ));
            if ($finalResponse === null) {
                return;
            }

            $this->refreshCarePlan();

            session()->flash('success', __('care-plan.care_plan_cancelled'));
            $this->showSignatureModal = false;
            $this->redirectAfterCarePlanClosed();

        } catch (EHealthJobTimeoutException $exception) {
            $exception->report();
            Session::flash('error', $exception->getMessage());
            $this->showSignatureModal = false;
        } catch (EHealthConnectionException $exception) {
            Log::error('CarePlanShow: connection error: ' . $exception->getMessage());
            Session::flash('error', __('care-plan.connection_error'));
            $this->showSignatureModal = false;
        } catch (EHealthValidationException|EHealthResponseException $exception) {
            if (method_exists($exception, 'report')) {
                $exception->report();
            }
            Log::error('CarePlanShow: eHealth error: ' . $exception->getMessage(), [
                'details' => method_exists($exception, 'getDetails') ? $exception->getDetails() : null
            ]);
            $msg = $exception instanceof EHealthValidationException
                ? $exception->getFormattedMessage()
                : __('care-plan.ehealth_error_prefix') . $exception->getMessage();

            if ($this->actionType === 'cancel' && $this->isCarePlanAlreadyCancelledError($exception)) {
                $repository->updateById($this->carePlan->id, [
                    'status' => CarePlanStatus::CANCELLED->value,
                ]);
                $this->refreshCarePlan();
            }

            Session::flash('error', $msg);
            $this->showSignatureModal = false;
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan status change');
            $this->showSignatureModal = false;
        } catch (Throwable $exception) {
            Log::error('CarePlanShow: unexpected error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'actionType' => $this->actionType,
            ]);
            Session::flash(
                'error',
                __('care-plan.unexpected_error') . ' ' . $exception->getMessage()
            );
            $this->showSignatureModal = false;
        }
    }

    protected function signPlan(CarePlanRepository $repository, array $signingData): void
    {
        if (Auth::user()?->can('create', CarePlan::class) !== true) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return;
        }

        try {
            $repository->assertDraftSigningReady($this->carePlan);
            $resource = $repository->getResource($this->carePlan->id);
            $api = EHealth::carePlan();
            $signedContent = signatureService()->signData(
                $api->prepareCreateContent($resource),
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                Auth::user()->party->taxId
            );
            $result = $this->submitClinicalOperation(
                $this->carePlan,
                ['status' => CarePlanStatus::PENDING->value],
                fn () => $api->create($this->carePlan->person->uuid, [
                    'signed_data' => $signedContent,
                    'signed_data_encoding' => 'base64',
                ], $this->carePlan->uuid),
                operationContext: ['expected' => $resource]
            );
            if ($result === null) {
                $this->activateAfterSubmission = true;
                $this->planSubmission = $repository->submissionState($this->carePlan);

                return;
            }
            $this->refreshCarePlan();
            Session::flash('success', __('care-plan.signed_and_sent'));
            $this->showSignatureModal = false;
            $this->activateAfterSubmission = true;
            $this->continuePlanActivation();
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan');
            $this->planSubmission = $repository->submissionState($this->carePlan);
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('error', match (true) {
                $exception instanceof ValidationException => $exception->validator->errors()->first(),
                $exception instanceof EHealthValidationException => $exception->getFormattedMessage(),
                default => __('care-plan.unexpected_error'),
            });
            $this->planSubmission = $repository->submissionState($this->carePlan);
            $this->showSignatureModal = false;
        }
    }

    public function completePlan(CarePlanRepository $repository): void
    {
        if (Auth::user()?->can('complete', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }
        $this->authorizeCarePlanWrite();
        if ($this->guardPlanLifecycleAccess('complete')) {
            return;
        }
        $this->validate([
            'statusReason' => 'required|string'
        ]);

        if (empty($this->carePlan->uuid)) {
            Session::flash('error', __('care-plan.care_plan_not_synced'));
            $this->showSignatureModal = false;

            return;
        }

        $blockReason = app(CarePlanLifecycleGateService::class)
            ->planCompleteBlockReason($this->carePlan);

        if ($blockReason !== null) {
            Session::flash('error', $blockReason);
            $this->showSignatureModal = false;

            return;
        }

        $statusReasonCodeableConcept = [
            'coding' => [
                [
                    'system' => 'eHealth/care_plan_complete_reasons',
                    'code' => $this->statusReason,
                ]
            ]
        ];

        try {
            $finalResponse = $this->submitClinicalOperation($this->carePlan, ['status' => CarePlanStatus::COMPLETED->value, 'statusReason' => $statusReasonCodeableConcept], fn () => EHealth::carePlan()->complete(
                $this->carePlan->person->uuid,
                $this->carePlan->uuid,
                [
                    'status_reason' => $statusReasonCodeableConcept,
                ]
            ));
            if ($finalResponse === null) {
                return;
            }

            $this->refreshCarePlan();

            session()->flash('success', __('care-plan.care_plan_completed'));
            $this->showSignatureModal = false;
            $this->redirectAfterCarePlanClosed();

        } catch (EHealthJobTimeoutException $exception) {
            $exception->report();
            Session::flash('error', $exception->getMessage());
            $this->showSignatureModal = false;
        } catch (EHealthConnectionException $exception) {
            Log::error('CarePlanShow: connection error: ' . $exception->getMessage());
            Session::flash('error', __('care-plan.connection_error'));
        } catch (EHealthValidationException|EHealthResponseException $exception) {
            if (method_exists($exception, 'report')) {
                $exception->report();
            }
            Log::error('CarePlanShow: eHealth error: ' . $exception->getMessage(), [
                'details' => method_exists($exception, 'getDetails') ? $exception->getDetails() : null
            ]);
            $msg = $exception instanceof EHealthValidationException
                ? $exception->getFormattedMessage()
                : __('care-plan.ehealth_error_prefix') . $exception->getMessage();
            Session::flash('error', $msg);
        } catch (Throwable $exception) {
            Log::error('CarePlanShow: unexpected error: ' . $exception->getMessage());
            Session::flash('error', __('care-plan.unexpected_error'));
        }
    }

    protected function signActivity(
        CarePlanRepository $repository,
        CarePlanActivityRepository $activityRepository,
        array $signingData
    ): void {
        $this->authorizeCarePlanActivityWrite();
        try {
            $activity = $this->ownedActivity((int) $this->activityToSign);
            if ($activity->status !== CarePlanStatus::DRAFT->value) {
                throw ValidationException::withMessages(['activityToSign' => __('care-plan.activity_signed')]);
            }
            if (method_exists($this, 'getDeviceSignReadinessWarning')) {
                $warning = $this->getDeviceSignReadinessWarning($activity);
                if ($warning !== null) {
                    throw ValidationException::withMessages(['activityToSign' => $warning]);
                }
            }
            $resource = $activityRepository->getResource($activity->id);
            $payload = CarePlanActivityForm::buildCreateContent($resource);
            $signedContent = signatureService()->signData($payload, $signingData['knedp'], $signingData['keyContainer'], $signingData['password'], Auth::user()->party->taxId);
            $result = $this->submitClinicalOperation($activity, ['status' => 'scheduled'], fn () => EHealth::carePlanActivity()->create($this->carePlan->person->uuid, $this->carePlan->uuid, ['signed_data' => $signedContent, 'signed_data_encoding' => 'base64']));
            if ($result === null) {
                $this->redirectRoute('care-plans.activities.show', [legalEntity(), $this->carePlan->id, $activity->id], navigate: true);

                return;
            }
            $this->syncPlanStatus();
            $this->refreshCarePlan();
            Session::flash('success', __('care-plan.activity_signed'));
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan activity');
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('error', $exception instanceof EHealthValidationException ? $exception->getFormattedMessage() : ($exception instanceof ValidationException ? $exception->validator->errors()->first() : __('care-plan.unexpected_error')));
        } finally {
            $this->showSignatureModal = false;
        }
    }

    protected function signStatusActivity(CarePlanActivityRepository $activityRepository, array $signingData): void
    {
        $this->authorizeCarePlanActivityWrite();
        if (!$this->activityToSign) {
            Session::flash('error', __('care-plan.no_activity_selected'));

            return;
        }
        $activity = $this->ownedActivity((int) $this->activityToSign);
        $blockReason = app(CarePlanLifecycleGateService::class)->activityStatusChangeBlockReason($activity, $this->actionType);
        if ($blockReason !== null) {
            Session::flash('error', $blockReason);

            return;
        }
        try {
            $complete = $this->actionType === 'complete_activity';
            $reason = FhirResource::make()->coding($complete ? 'eHealth/care_plan_activity_complete_reasons' : 'eHealth/care_plan_activity_cancel_reasons', $this->statusReason)->toCodeableConcept();
            $api = EHealth::carePlanActivity();
            $outcomes = [];
            if ($complete) {
                $this->validate(['outcomeCode' => ['required', 'string']]);
                if ($this->outcomeReferences !== []) {
                    $this->addError('outcomeReferences', __('Для завершення оберіть результат із довідника. Посилання на записи не передаються цією операцією.'));

                    return;
                }
                $outcomes = [FhirResource::make()->coding('eHealth/care_plan_activity_outcomes', $this->outcomeCode)->toCodeableConcept()];
                $payload = CarePlanActivityForm::buildCompleteContent($reason, $outcomes);
                $result = $this->submitClinicalOperation($activity, ['status' => 'completed', 'statusReason' => $reason, 'outcomeCodeableConcept' => $outcomes], fn () => $api->complete($this->carePlan->person->uuid, $this->carePlan->uuid, $activity->uuid, $payload));
            } else {
                $snapshot = $this->fetchActivityCancelSnapshot($this->carePlan->person->uuid, $this->carePlan->uuid, $activity->uuid);
                $content = CarePlanActivityForm::buildCancelContent($snapshot, $reason);
                $signed = signatureService()->signData($content, $signingData['knedp'], $signingData['keyContainer'], $signingData['password'], Auth::user()->party->taxId);
                $result = $this->submitClinicalOperation($activity, ['status' => 'cancelled', 'statusReason' => $reason], fn () => $api->cancel($this->carePlan->person->uuid, $this->carePlan->uuid, $activity->uuid, ['signed_data' => $signed, 'signed_data_encoding' => 'base64']));
            }
            if ($result === null) {
                return;
            }
            $this->refreshCarePlan();
            Session::flash('success', __($complete ? 'care-plan.activity_completed' : 'care-plan.activity_cancelled'));
            $this->showSignatureModal = false;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan activity cancellation');
            $this->showSignatureModal = false;
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('error', $exception instanceof EHealthValidationException ? $exception->getFormattedMessage() : __('care-plan.unexpected_error'));
            $this->showSignatureModal = false;
        }
    }

    public function openMethodSelectionModal(): void
    {
        if (!$this->guardApprovalAccess($this->carePlan)) {
            return;
        }
        if ($this->guardTerminalCarePlanMutation()) {
            return;
        }

        try {
            Repository::carePlan()->assertApprovalCreationReady($this->carePlan);
        } catch (ValidationException $exception) {
            Session::flash('error', $exception->validator->errors()->first());

            return;
        }

        if (empty($this->carePlan->uuid)) {
            Session::flash('error', __('План лікування ще не синхронізовано з ЕСОЗ.'));

            return;
        }

        $service = app(CarePlanApprovalService::class);
        if ($service->skipsPatientOtp($this->carePlan)) {
            $submission = Repository::carePlan()->latestSubmissionLink($this->carePlan);
            if ($submission === null) {
                $this->createApproval('');

                return;
            }
            try {
                $employeeUuid = $this->currentCarePlanEmployee()?->uuid;
                $bearerToken = session()->get(config('ehealth.api.oauth.bearer_token'));
                if (!$employeeUuid || !$bearerToken) {
                    throw new RuntimeException(__('care-plan.approval_create_error'));
                }
                Repository::carePlan()->queueInpatientApproval($submission, legalEntity(), Auth::user(), $bearerToken, $employeeUuid);
                $this->inpatientApprovalStatus = 'queued';
                Session::flash('info', __('care-plan.inpatient_access_processing'));
            } catch (Throwable $exception) {
                report($exception);
                Session::flash('error', __('care-plan.inpatient_access_failed'));
            }

            return;
        }

        try {
            $methods = EHealth::person()->getAuthMethods($this->carePlan->person->uuid)->validate();
            $this->authMethods = app(PatientApprovalService::class)
                ->enrichAuthenticationMethods($methods, $this->carePlan->person);
            $this->showMethodSelectionModal = true;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to load auth methods: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося завантажити методи аутентифікації'));
        }
    }

    protected function isCarePlanAlreadyCancelledError(Throwable $exception): bool
    {
        if ($exception instanceof EHealthValidationException) {
            return $exception->isCarePlanAlreadyCancelled();
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'cannot be cancelled')
            && str_contains($message, 'cancelled');
    }

    protected function redirectAfterCarePlanClosed(): void
    {
        $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);

        $personId = $this->carePlan->personId;
        $encounterId = $this->carePlan->encounterId;

        if ($personId && $encounterId) {
            $this->redirectRoute('persons.encounters.package.show', [legalEntity(), $personId, $encounterId], navigate: true);

            return;
        }

        if ($personId) {
            $this->redirectRoute('persons.care-plans', [legalEntity(), $personId], navigate: true);
        }
    }

    public function selectAuthMethod(string $methodUuid): void
    {
        if (!$this->guardApprovalAccess($this->carePlan)) {
            return;
        }
        $this->currentAuthMethod = $this->selectedApprovalAuthMethod($methodUuid);
        $this->showMethodSelectionModal = false;
        $this->createApproval($methodUuid);
    }

    protected function createApproval(string $methodUuid): void
    {
        try {
            $employeeUuid = $this->currentCarePlanEmployee()?->uuid;

            if (!$employeeUuid) {
                Session::flash('error', __('Не вдалося визначити лікаря для створення дозволу.'));

                return;
            }

            $service = app(CarePlanApprovalService::class);
            $result = $service->create(
                carePlan: $this->carePlan,
                patientUuid: $this->carePlan->person->uuid,
                employeeUuid: $employeeUuid,
                accessLevel: 'write',
                authorizeWith: $service->skipsPatientOtp($this->carePlan) ? null : ($methodUuid ?: null),
                user: Auth::user(),
                bearerToken: session()->get(config('ehealth.api.oauth.bearer_token')),
            );

            if ($result->isAsync()) {
                $this->approvalId = $result->approvalId;
                $this->pollingLinkId = $result->pollingLinkId;
                $this->isPolling = true;
                Session::flash('info', __('care-plan.approval_access_processing'));

                return;
            }

            $this->approvalId = $result->approvalId;

            if ($result->requiresConfirmation()) {
                $this->currentAuthMethod = $result->authMethod ?? $this->currentAuthMethod;
                $this->openAuthModal();

                return;
            }

            $this->refreshCarePlan();
            $this->dispatch('refreshApprovals');
            Session::flash(
                'success',
                $service->skipsPatientOtp($this->carePlan)
                    ? __('care-plan.approval_inpatient_granted')
                    : __('План лікування успішно активовано.')
            );
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to create approval: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося створити запит на дозвіл: ') . $e->getMessage());
        }
    }

    public function checkApprovalJobStatus(): void
    {
        if (!$this->isPolling || !$this->pollingLinkId) {
            return;
        }

        $status = app(CarePlanApprovalService::class)->resolveAsyncJob($this->pollingLinkId);

        if ($status->isPending()) {
            return;
        }

        $this->isPolling = false;
        $this->pollingLinkId = null;

        if ($status->isFailed()) {
            Session::flash('error', $status->errorMessage ?: __('Не вдалося обробити запит на дозвіл.'));

            return;
        }

        if ($status->approvalId) {
            $this->approvalId = $status->approvalId;
        }

        $service = app(CarePlanApprovalService::class);
        if ($status->requiresConfirmation()) {
            $this->currentAuthMethod = $status->authMethod ?? $this->currentAuthMethod;
            $this->openAuthModal();

            return;
        }

        $this->refreshCarePlan();
        $this->dispatch('refreshApprovals');
        Session::flash(
            'success',
            $service->skipsPatientOtp($this->carePlan)
                ? __('care-plan.approval_inpatient_granted')
                : __('План лікування успішно активовано.')
        );
    }

    public function verify(): void
    {
        if ($this->isSmsResending) {
            Session::flash('info', __('care-plan.sms_resend_processing'));

            return;
        }
        if (!$this->guardApprovalAccess($this->carePlan)) {
            return;
        }
        app(MedicalRequestOwnership::class)->approvalForCarePlan($this->carePlan, $this->approvalId ?? '');
        try {
            $this->validate($this->approvalVerificationRules(), $this->approvalVerificationMessages());
        } catch (ValidationException $exception) {
            Session::flash('error', $exception->validator->errors()->first());
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        try {
            app(CarePlanApprovalService::class)->verify(
                $this->carePlan->person->uuid,
                $this->approvalId,
                $this->isOfflineAuthMethod() ? null : (int) $this->verificationCode,
            );
        } catch (Exception $e) {
            Log::warning('Care plan approval confirmation failed', ['exceptionType' => $e::class, 'statusCode' => $e->getCode()]);
            $message = $e instanceof EHealthValidationException ? $e->getFormattedMessage()
                : ($e instanceof EHealthResponseException && ApprovalApi::errorMessage($e->response) === 'Invalid verification code'
                    ? __('errors.ehealth.messages.invalid_verification_code') : __('care-plan.approval_verify_error'));
            $this->addError('verificationCode', $message);
            Session::flash('error', $message);

            return;
        }
        $this->resetValidation('verificationCode');
        $this->closeAuthModal();
        try {
            $this->refreshCarePlan();
            $this->dispatch('refreshApprovals');
        } catch (Throwable $exception) {
            Log::warning('Verified care plan approval view could not be refreshed', ['exceptionType' => $exception::class]);
            Session::flash('warning', __('care-plan.approval_verified_refresh_pending'));

            return;
        }
        Session::flash('success', __('care-plan.approval_verified'));
    }

    protected function carePlanForSms(): CarePlan
    {
        return $this->carePlan;
    }

    protected function onSmsApprovalAlreadyGranted(): void
    {
        $this->refreshCarePlan();
        $this->dispatch('refreshApprovals');
    }

    #[Locked]
    public ?string $planSyncBatchId = null;

    public function sync(): void
    {
        if (Auth::user()->cannot('view', $this->carePlan)) {
            Session::flash('error', __('care-plan.sync_access_denied'));

            return;
        }
        if (empty($this->carePlan->uuid)) {
            Session::flash('error', __('care-plan.care_plan_not_synced'));

            return;
        }

        if ($this->planSyncBatchId !== null) {
            $this->checkPlanSyncStatus();

            return;
        }
        $token = session()->get(config('ehealth.api.oauth.bearer_token'));
        if (!is_string($token) || $token === '') {
            Session::flash('error', __('care-plan.sync_session_expired'));

            return;
        }
        $batch = Bus::batch([new CarePlanFullSync(
            legalEntity(),
            standalone: true,
            resourceUuid: $this->carePlan->uuid,
            patientContext: [
                'patient_uuid' => $this->carePlan->person->uuid,
                'person_id' => $this->carePlan->personId,
                'patient_care_plan_registry' => true,
                'care_plan_approvals' => true,
            ],
        )])
            ->withOption('legal_entity_id', legalEntity()->id)
            ->withOption('patient_uuid', $this->carePlan->person->uuid)
            ->withOption('person_id', $this->carePlan->personId)
            ->withOption('patient_care_plan_registry', true)
            ->withOption('care_plan_approvals', true)
            ->withOption('sync_entity', LegalEntity::ENTITY_CARE_PLAN)
            ->withOption('token', Crypt::encryptString($token))
            ->withOption('user', Auth::user())
            ->name(CarePlanFullSync::BATCH_NAME)->onQueue('sync')->dispatch();
        $this->planSyncBatchId = $batch->id;
        Session::flash('info', __('care-plan.sync_processing'));
    }

    public function checkPlanSyncStatus(): void
    {
        if ($this->planSyncBatchId === null) {
            return;
        }
        $batch = Bus::findBatch($this->planSyncBatchId);
        if ($batch !== null && !$batch->finished() && !$batch->cancelled()) {
            return;
        }
        $this->planSyncBatchId = null;
        $this->refreshCarePlan();
        $this->dispatch('refreshApprovals');
        if ($batch !== null && (int) Cache::get('care-plan-sync-denied:'.$batch->id, 0) > 0) {
            Session::flash('warning', __('care-plan.sync_partial_access', [
                'count' => (int) Cache::get('care-plan-sync-denied:'.$batch->id, 0),
            ]));

            return;
        }
        Session::flash(
            $batch !== null && !$batch->hasFailures() && !$batch->cancelled() ? 'success' : 'error',
            __($batch !== null && !$batch->hasFailures() && !$batch->cancelled() ? 'care-plan.data_synced' : 'care-plan.sync_error')
        );
    }

    protected function buildCarePlanStatusChangePayload(array $statusReasonCodeableConcept): array
    {
        return CarePlanForm::buildCancelContent($this->fetchCarePlanCancelSnapshot($this->carePlan->person->uuid, $this->carePlan->uuid), $statusReasonCodeableConcept);
    }

    public function syncPlanStatus(): bool
    {
        try {
            $this->synchronizeResources([['id' => $this->carePlan->uuid]], $this->carePlan->personId, $this->carePlan->legalEntityId, $this->carePlan->person->uuid);
            app(CarePlanApprovalService::class)->syncForCarePlan($this->carePlan);
            $this->refreshCarePlan();
            $this->dispatch('refreshApprovals');

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    #[Locked]
    public ?int $smsResendLinkId = null;

    #[Locked]
    public bool $isSmsResending = false;

    public function resendSms(): void
    {
        if (!$this->approvalId || $this->smsResent || $this->isSmsResending
            || AuthenticationMethod::confirmationMethod($this->currentAuthMethod ?? []) !== AuthenticationMethod::OTP) {
            return;
        }
        try {
            $plan = $this->carePlanForSms();
            if (!$this->guardApprovalAccess($plan)) {
                return;
            }
            app(MedicalRequestOwnership::class)->approvalForCarePlan($plan, $this->approvalId);
            $this->resetValidation('verificationCode');
            $this->isSmsResending = true;
            $token = session()->get(config('ehealth.api.oauth.bearer_token'));
            if (!$token || !Auth::user() || !legalEntity()) {
                throw new RuntimeException(__('care-plan.sync_session_expired'));
            }
            $result = app(CarePlanApprovalService::class)->requestSmsResend($plan, $this->approvalId, legalEntity(), Auth::user(), $token);
            if ($result['status'] === 'pending') {
                $this->smsResendLinkId = $result['linkId'];
                Session::flash('info', __('care-plan.sms_resend_processing'));

                return;
            }
            $this->finishSmsResend($result['status']);
        } catch (Throwable $exception) {
            $this->isSmsResending = false;
            Log::warning('Care plan approval SMS resend failed', ['exceptionType' => $exception::class]);
            $message = $exception instanceof EHealthValidationException ? $exception->getFormattedMessage()
                : ($exception instanceof RuntimeException ? $exception->getMessage() : __('care-plan.sms_resend_error'));
            $this->addError('verificationCode', $message);
            Session::flash('error', $message);
        }
    }

    public function checkSmsResendStatus(): void
    {
        if ($this->smsResendLinkId === null || !$this->approvalId) {
            return;
        }
        $plan = $this->carePlanForSms();
        $this->authorize('view', $plan);
        $state = MedicalEventsRepository::approval()->carePlanSmsResendState($plan, $this->approvalId, $this->smsResendLinkId);
        if (in_array($state['status'], ['PENDING', 'PROCESSING'], true)) {
            return;
        }
        $this->finishSmsResend(strtolower($state['status']));
    }

    protected function finishSmsResend(string $status): void
    {
        $this->smsResendLinkId = null;
        $this->isSmsResending = false;
        if ($status === 'granted') {
            $this->closeAuthModal();
            Session::flash('success', __('care-plan.approval_verified'));
            $this->onSmsApprovalAlreadyGranted();
        } elseif ($status === 'processed') {
            $this->smsResent = true;
            $this->verificationCode = '';
            $this->resetValidation('verificationCode');
            Session::flash('success', __('care-plan.sms_resent'));
        } else {
            $this->smsResent = false;
            $this->addError('verificationCode', __('care-plan.sms_resend_error'));
            Session::flash('error', __('care-plan.sms_resend_error'));
        }
    }

    public function initEPrescriptionForm(int $activityId, CarePlanActivityRepository $activityRepository): void
    {
        $this->authorizeCarePlanActivityWrite();

        try {
            $activity = $this->ownedActivity($activityId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        $activityStatus = strtolower(is_array($activity->status)
            ? ($activity->status['coding'][0]['code'] ?? ($activity->status['text'] ?? ''))
            : (string) $activity->status);

        $blockedActivityStatuses = ['cancelled', 'completed'];

        if ($this->isTerminalCarePlan) {
            Session::flash('error', __('care-plan.cannot_mutate_terminal_care_plan', [
                'status' => CarePlanStatus::labelFor($this->carePlan->status),
            ]));

            return;
        }

        if (in_array($activityStatus, $blockedActivityStatuses)) {
            Session::flash('error', __('Виписування рецепту заборонено: це призначення вже завершено або скасовано.'));

            return;
        }

        if ($activity->resolvedKind() !== 'medication_request') {
            Session::flash('error', __('care-plan.eprescription_wrong_activity_kind'));

            return;
        }

        try {
            app(CarePlanActivityEHealthGuard::class)->assertRegisteredInEHealth($this->carePlan, $activity);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return;
        }

        $this->ePrescriptionSelectedProduct = null;
        $this->ePrescriptionWarningMessage = '';
        $this->ePrescriptionPackages = [];
        $this->ePrescriptionMultiples = [];

        try {
            $this->ePrescriptionSelectedProduct = $this->resolveDrugForActivity($activity);
            if ($this->ePrescriptionSelectedProduct && !empty($this->ePrescriptionSelectedProduct['packages'])) {
                $this->ePrescriptionPackages = $this->ePrescriptionSelectedProduct['packages'];
                $minQty = $this->resolveMedicationPackageStep($this->ePrescriptionSelectedProduct);
                $multiples = [];
                for ($i = 1; $i <= 10; $i++) {
                    $multiples[] = $minQty * $i;
                }
                $this->ePrescriptionMultiples = $multiples;
            }
        } catch (Exception $e) {
            Log::warning('CarePlanShow: failed to fetch drug details: ' . $e->getMessage());
        }

        if (!$this->ePrescriptionSelectedProduct) {
            $this->ePrescriptionSelectedProduct = [
                'name' => $activity->productReference,
                'innm_dosage_form' => 'од.',
            ];
        }

        $this->ePrescriptionSelectedProgram = null;
        $this->ePrescriptionSkipTreatmentPeriod = true;
        if (!empty($activity->program)) {
            $program = dictionary()->medicalPrograms()->firstWhere('id', $activity->program);
            if ($program) {
                $this->ePrescriptionSelectedProgram = $program;
                $settings = $this->ePrescriptionSelectedProgram['settings'] ?? [];
                $this->ePrescriptionSkipTreatmentPeriod = filter_var($settings['skip_treatment_period'] ?? true, FILTER_VALIDATE_BOOLEAN);
            }
        }

        // The patient's authentication method is legally significant: it must come from the
        // eHealth registry, never from a local fallback.
        $this->ePrescriptionAuthMethods = [];
        try {
            $this->ePrescriptionAuthMethods = EHealth::person()->getAuthMethods($this->carePlan->person->uuid)->getData();
        } catch (Exception $e) {
            Log::channel('e_health_errors')->error('CarePlanShow: failed to fetch patient auth methods', [
                'care_plan_id' => $this->carePlan->id,
                'activity_id' => $activity->id,
                'exception' => $e->getMessage(),
            ]);
            Session::flash('error', __('care-plan.auth_methods_unavailable'));

            return;
        }

        if (empty($this->ePrescriptionAuthMethods)) {
            Session::flash('error', __('care-plan.auth_methods_empty'));

            return;
        }

        $issuedQty = MedicalEventsRepository::medicationRequest()->issuedCarePlanQuantity($activity->uuid);

        $activityQty = $activity->quantity;
        $this->ePrescriptionRemainingQty = $activityQty === null
            ? 1.0
            : max(0.0, (float) $activityQty - (float) $issuedQty);

        try {
            $eHealthActivity = EHealth::carePlanActivity()->getDetails(
                (string) $this->carePlan->person->uuid,
                (string) $this->carePlan->uuid,
                (string) $activity->uuid
            )->validate();
            if ($eHealthActivity['id'] !== $activity->uuid) {
                throw new RuntimeException('Activity details do not match the requested resource.');
            }
            $eHealthRemaining = data_get($eHealthActivity, 'detail.remainingQuantity.value');
            if ($eHealthRemaining !== null) {
                $this->ePrescriptionRemainingQty = max(0.0, (float) $eHealthRemaining);
            }
        } catch (Exception $e) {
            Log::warning('CarePlanShow: failed to fetch eHealth activity remaining qty: ' . $e->getMessage());
        }

        if ($activityQty === null) {
            $this->ePrescriptionWarningMessage = __('У призначенні плану лікування не вказано кількість. Перевірте дані в ЕСОЗ перед підписанням рецепту.');
        }

        $unit = $this->ePrescriptionSelectedProduct['innm_dosage_form'] ?? 'од.';
        $packageStep = $this->resolveMedicationPackageStep($this->ePrescriptionSelectedProduct ?? []);

        if ($packageStep > 0 && $this->ePrescriptionRemainingQty > 0 && $this->ePrescriptionRemainingQty < $packageStep) {
            $message = __('care-plan.medication_remaining_below_packaging', [
                'remaining' => $this->ePrescriptionRemainingQty,
                'count' => $packageStep,
            ]);
            Session::flash('error', $message);

            return;
        }

        $defaultQty = !empty($this->ePrescriptionMultiples)
            ? $this->ePrescriptionMultiples[0]
            : $packageStep;

        if ($this->ePrescriptionRemainingQty > 0 && $defaultQty > $this->ePrescriptionRemainingQty) {
            $defaultQty = $this->ePrescriptionRemainingQty;
            if (!$this->isMedicationQtyDivisible($defaultQty, $this->ePrescriptionSelectedProduct ?? [])) {
                $defaultQty = $packageStep <= $this->ePrescriptionRemainingQty
                    ? $packageStep
                    : $this->ePrescriptionRemainingQty;
            }
        }

        $this->ePrescriptionShowDailyDoseWarning = false;
        $this->ePrescriptionShowRemainingQtyWarning = false;
        $this->ePrescriptionRemainingQtyWarningMessage = '';
        $this->ePrescriptionSelectedActivity = $activity->toArray();

        $employeeContext = app(MedicationRequestLifecycleService::class)
            ->resolveEmployeeContext($this->carePlan, null, Auth::user()->activeDoctorEmployee()?->id);
        $eligibleEncounters = app(MedicationRequestLifecycleService::class)
            ->findEligibleEncountersForEPrescription(
                (int) $this->carePlan->person_id,
                $employeeContext['employee_uuid'] ?? null
            );

        $this->ePrescriptionEligibleEncounters = $eligibleEncounters
            ->map(static function ($encounter): array {
                $endedAt = $encounter->period?->end;
                $dateLabel = $endedAt
                    ? Carbon::parse($endedAt)->format('d.m.Y H:i')
                    : ($encounter->created_at?->format('d.m.Y H:i') ?? '');

                return [
                    'id' => (int) $encounter->id,
                    'uuid' => (string) $encounter->uuid,
                    'label' => trim(__('Завершена взаємодія від :date (:uuid)', ['date' => $dateLabel, 'uuid' => $encounter->uuid])),
                ];
            })
            ->values()
            ->all();

        $defaultEncounterId = count($this->ePrescriptionEligibleEncounters) === 1
            ? (string) $this->ePrescriptionEligibleEncounters[0]['id']
            : '';

        // eHealth MedicationRequest expects a drug id; activity.productReference is INNM dosage.
        $resolvedMedicationId = (string) ($this->ePrescriptionSelectedProduct['id'] ?? $activity->productReference);

        $this->ePrescriptionForm = [
            'activity_id' => $activity->id,
            'encounter_id' => $defaultEncounterId,
            'medication_id' => $resolvedMedicationId,
            'started_at' => now()->toDateString(),
            'duration' => 10,
            'ended_at' => '',
            'medication_qty' => $defaultQty,
            'medication_unit' => $unit,
            'signature_text' => '',
            'max_dose_per_period' => (float) $activity->dailyAmount ?: 1.0,
            'max_dose_per_administration' => 1.0,
            'inform_with' => !empty($this->ePrescriptionAuthMethods) ? ($this->ePrescriptionAuthMethods[0]['uuid'] ?? '') : '',
            'container_dosage' => '',
            'program_id' => $activity->program,
            'note' => '',
            'route' => 'oral',
        ];

        // Prefer OTP/THIRD_PERSON option value with pipe encoding when available.
        if (!empty($this->ePrescriptionAuthMethods)) {
            $first = $this->ePrescriptionAuthMethods[0];
            $methodId = $first['uuid'] ?? $first['id'] ?? '';
            $type = $first['type'] ?? '';
            $valueLabel = $first['phone_number'] ?? $first['alias'] ?? '';
            if ($methodId !== '') {
                $this->ePrescriptionForm['inform_with'] = "{$methodId}|{$type}|{$valueLabel}";
            }
        }

        if ($this->ePrescriptionEligibleEncounters === []) {
            Session::flash('error', __('care-plan.eprescription_encounter_none'));

            return;
        }

        $this->calculateTreatmentDates();
        $this->showEPrescriptionDrawer = true;
    }

    public function updatedEPrescriptionForm($value, $name): void
    {
        $this->ePrescriptionWarningMessage = '';
        $this->ePrescriptionShowDailyDoseWarning = false;

        if (str_contains($name, 'started_at') || str_contains($name, 'duration')) {
            $this->calculateTreatmentDates();
        }
    }

    public function calculateTreatmentDates(): void
    {
        if (empty($this->ePrescriptionForm['started_at']) || empty($this->ePrescriptionForm['duration'])) {
            return;
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d', $this->ePrescriptionForm['started_at']);
            $duration = (int) $this->ePrescriptionForm['duration'];

            if ($duration < 1) {
                return;
            }

            $maxPeriod = (int) ($this->ePrescriptionSelectedProgram['settings']['request_max_period_day'] ?? 90);
            if ($duration > $maxPeriod) {
                $this->ePrescriptionWarningMessage = __('Тривалість курсу лікування (:duration днів) перевищує максимальний період курсу за обраною програмою (:max_period днів).', ['duration' => $duration, 'max_period' => $maxPeriod]);
            } else {
                $this->ePrescriptionWarningMessage = '';
            }

            $end = $start->copy()->addDays($duration - 1);
            $this->ePrescriptionForm['ended_at'] = $end->toDateString();
        } catch (Exception $e) {
            // Invalid date format
        }
    }

    public function confirmExceededDailyDose(bool $confirm): void
    {
        $this->ePrescriptionShowDailyDoseWarning = false;
        if ($confirm) {
            if (!str_starts_with($this->ePrescriptionForm['signature_text'], '(!)')) {
                $this->ePrescriptionForm['signature_text'] = '(!) ' . $this->ePrescriptionForm['signature_text'];
            }
            $this->submitEPrescriptionRequest();
        }
    }

    public function validateEPrescription(): void
    {
        $this->ePrescriptionWarningMessage = '';
        $this->ePrescriptionShowDailyDoseWarning = false;

        try {
            $this->validate([
                'ePrescriptionForm.encounter_id' => 'required|integer|min:1',
                'ePrescriptionForm.medication_id' => 'required|string|uuid',
                'ePrescriptionForm.inform_with' => 'required|string',
                'ePrescriptionForm.signature_text' => 'required|string',
                'ePrescriptionForm.max_dose_per_administration' => 'required|numeric|min:0.01',
                'ePrescriptionForm.max_dose_per_period' => 'required|numeric|min:0.01',
                'ePrescriptionForm.medication_qty' => 'required|numeric|min:0.01',
                'ePrescriptionForm.started_at' => 'required|date',
                'ePrescriptionForm.duration' => 'required|integer|min:1',
                'ePrescriptionForm.ended_at' => 'required|date|after_or_equal:ePrescriptionForm.started_at',
            ], [
                'ePrescriptionForm.encounter_id.required' => __('care-plan.eprescription_encounter_required'),
                'ePrescriptionForm.signature_text.required' => __('care-plan.eprescription_signature_required'),
                'ePrescriptionForm.ended_at.after_or_equal' => __('errors.ehealth.messages.period_end_before_start'),
            ], [
                'ePrescriptionForm.encounter_id' => __('care-plan.encounter'),
                'ePrescriptionForm.medication_id' => __('care-plan.medication'),
                'ePrescriptionForm.inform_with' => __('encounters.eprescription.patient_authentication'),
                'ePrescriptionForm.signature_text' => __('medication-requests.signatura'),
                'ePrescriptionForm.max_dose_per_administration' => __('encounters.eprescription.max_dose_per_administration'),
                'ePrescriptionForm.max_dose_per_period' => __('encounters.eprescription.max_dose_per_period'),
                'ePrescriptionForm.medication_qty' => __('medication-requests.kil_kist'),
                'ePrescriptionForm.started_at' => __('encounters.eprescription.course_start'),
                'ePrescriptionForm.duration' => __('medication-requests.treatment_duration'),
                'ePrescriptionForm.ended_at' => __('encounters.eprescription.course_end'),
            ]);
        } catch (ValidationException $exception) {
            $this->ePrescriptionWarningMessage = $exception->validator->errors()->first();
            $this->setErrorBag($exception->validator->getMessageBag());
            Session::flash('error', $this->ePrescriptionWarningMessage);
            $this->dispatch('scroll-to-error');

            return;
        }

        $qty = (float) $this->ePrescriptionForm['medication_qty'];
        $maxDosage = (float) ($this->ePrescriptionSelectedProduct['packages'][0]['max_request_dosage'] ?? ($this->ePrescriptionSelectedProduct['max_request_dosage'] ?? 0));
        $packageStep = $this->resolveMedicationPackageStep($this->ePrescriptionSelectedProduct ?? []);

        if ($packageStep > 0 && !$this->isMedicationQtyDivisible($qty, $this->ePrescriptionSelectedProduct ?? [])) {
            $message = __('care-plan.medication_qty_packaging', ['count' => $packageStep]);
            $this->failEPrescriptionField($message, 'ePrescriptionForm.medication_qty');

            return;
        }

        if ($maxDosage > 0 && $qty > $maxDosage) {
            $unit = $this->ePrescriptionForm['medication_unit'] ?? '';
            $this->ePrescriptionWarningMessage = __('Увага! За даним рецептом перевищено максимально допустиму кількість лікарського засобу [:medication], що дозволена до виписування в 1 рецепті. Максимально допустима кількість ЛЗ становить :quantity :unit. Будь-ласка, поверніться та скоригуйте електронний рецепт!', ['medication' => $this->ePrescriptionSelectedProduct['name'], 'quantity' => $maxDosage, 'unit' => $unit]);
            Session::flash('error', $this->ePrescriptionWarningMessage);

            return;
        }

        if ($qty > $this->ePrescriptionRemainingQty && $this->ePrescriptionSelectedActivity['quantity'] !== null) {
            $this->ePrescriptionWarningMessage = __('Кількість ЛЗ в рецепті (:quantity) перевищує залишкову кількість у плані лікування (:remaining). Виписування неможливе.', ['quantity' => $qty, 'remaining' => $this->ePrescriptionRemainingQty]);
            Session::flash('error', $this->ePrescriptionWarningMessage);

            return;
        }

        if (!$this->ePrescriptionSkipTreatmentPeriod) {
            $lastActivePrescription = MedicalEventsRepository::medicationRequest()->lastActiveForTreatment($this->carePlan->personId, $this->ePrescriptionForm['medication_id']);

            if ($lastActivePrescription && $lastActivePrescription->endedAt) {
                $lastEnd = Carbon::parse($lastActivePrescription->endedAt);
                $today = now();
                $remainingDays = $today->diffInDays($lastEnd, false);

                if ($remainingDays > 0) {
                    $prevDuration = $lastActivePrescription->startedAt ? Carbon::parse($lastActivePrescription->startedAt)->diffInDays($lastEnd) + 1 : 10;
                    $allowedDaysBeforeEnd = $prevDuration >= 21 ? 7 : 3;

                    if ($remainingDays > $allowedDaysBeforeEnd) {
                        $this->ePrescriptionWarningMessage = __('Повторний Е-Рецепт на той же МНН можна виписати за :allowed_days днів до закінчення терміну лікування попереднього Е-Рецепту. Попередній рецепт діє до :date (залишилось :remaining_days днів).', ['allowed_days' => $allowedDaysBeforeEnd, 'date' => $lastEnd->format('d.m.Y'), 'remaining_days' => $remainingDays]);
                        Session::flash('error', $this->ePrescriptionWarningMessage);

                        return;
                    }
                }
            }
        }

        $dailyDose = (float) $this->ePrescriptionForm['max_dose_per_period'];
        $drugName = (string) ($this->ePrescriptionSelectedProduct['name'] ?? __('ЛЗ'));
        $unit = (string) ($this->ePrescriptionForm['medication_unit'] ?? 'од.');
        $maxDailyDosage = (float) ($this->ePrescriptionSelectedProduct['max_daily_dosage'] ?? 0);
        $recommendedDailyDose = (float) ($this->ePrescriptionSelectedProduct['daily_dosage'] ?? 0);
        $planDailyAmount = (float) ($this->ePrescriptionSelectedActivity['daily_amount'] ?? 0);

        if ($maxDailyDosage > 0 && $dailyDose > $maxDailyDosage) {
            $message = __('care-plan.eprescription_max_daily_dosage_exceeded', [
                'name' => $drugName,
                'max' => $maxDailyDosage,
                'unit' => $unit,
            ]);
            $this->ePrescriptionWarningMessage = $message;
            Session::flash('error', $message);

            return;
        }

        $exceededRecommended = $recommendedDailyDose > 0 && $dailyDose > $recommendedDailyDose;
        $exceededPlan = $planDailyAmount > 0 && $dailyDose > $planDailyAmount;

        if ($exceededRecommended || $exceededPlan) {
            $this->ePrescriptionShowDailyDoseWarning = true;
            $warning = $exceededPlan
                ? __('care-plan.eprescription_plan_daily_amount_warning', ['name' => $drugName])
                : __('care-plan.eprescription_daily_dose_warning', ['name' => $drugName]);
            $this->ePrescriptionWarningMessage = $warning;
            Session::flash('warning', $warning);

            return;
        }

        $this->submitEPrescriptionRequest();
    }

    public function submitEPrescriptionRequest(): void
    {
        try {
            $employeeContext = app(MedicationRequestLifecycleService::class)
                ->resolveEmployeeContext($this->carePlan, null, Auth::user()->activeDoctorEmployee()?->id);
            $activity = $this->ownedActivity((int) $this->ePrescriptionForm['activity_id']);

            $uuid = app(MedicationRequestLifecycleService::class)->createCarePlanDraft(
                $this->carePlan,
                $activity,
                $this->ePrescriptionForm,
                $employeeContext
            );

            $this->showEPrescriptionDrawer = false;
            $this->ePrescriptionRequestIdToSign = $uuid;
            Session::flash('success', __('Заявку на е-рецепт створено. Підпишіть КЕП.'));
            $this->openSignatureModal('sign_eprescription');

        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
        } catch (EHealthValidationException $exception) {
            $exception->report();
            Session::flash('error', $exception->getTranslatedMessage());
        } catch (EHealthResponseException $e) {
            if ($e->getCode() === 403 || $e->response->status() === 403) {
                Log::warning('CarePlanShow: 403 access denied when submitting ePrescription. Prompting for approval.');
                Session::flash('warning', __('Відсутній доступ до медичних даних. Будь ласка, надішліть запит на доступ пацієнту.'));
                $this->openMethodSelectionModal();
            } else {
                Log::error('CarePlanShow: failed to create ePrescription API error: ' . $e->getMessage());
                Session::flash('error', __('Не вдалося створити заявку на рецепт: ') . $e->getMessage());
            }
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to create ePrescription: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося створити заявку на рецепт: ') . $e->getMessage());
        }
    }

    public function syncEPrescriptions(): void
    {
        try {
            $personUuid = $this->carePlan->person->uuid ?? null;
            if (!$personUuid) {
                Session::flash('error', __('Не знайдено ідентифікатор пацієнта в ЕСОЗ'));

                return;
            }

            $activityUuids = $this->carePlan->activities->pluck('uuid')->toArray();
            $localRequests = Repository::carePlanActivity()->linkedDocuments($activityUuids, 'medication_request');

            if ($localRequests->isEmpty()) {
                Session::flash('info', __('Немає виписаних рецептів для синхронізації у цьому плані лікування'));

                return;
            }

            $activeItems = EHealth::medicationRequest()->getBySearchParams((string) $personUuid, [])->getData();
            $draftItems = EHealth::medicationRequest()->getRequestsBySearchParams((string) $personUuid, [])->getData();
            $updatedCount = MedicalEventsRepository::medicationRequest()->synchronizeCarePlanDocuments($localRequests, $activeItems, $draftItems);

            $this->refreshCarePlan();
            Session::flash('success', __('Синхронізовано з ЕСОЗ. Оновлено статусів: :count', ['count' => $updatedCount]));

        } catch (Exception $e) {
            Log::error('ManagesCarePlanEPrescription sync error: ' . $e->getMessage());
            Session::flash('error', __('Помилка при синхронізації з ЕСОЗ: ') . $e->getMessage());
        }
    }

    public function rejectPrescription(string $requestId): void
    {
        $this->authorizeCarePlanActivityWrite();

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->medicationForPerson($requestId, (int) $this->carePlan->personId);

            if (in_array(strtolower((string) $requestRecord->status), ['new', 'draft'], true)) {
                app(MedicationRequestLifecycleService::class)->rejectPrescription($this->carePlan, $requestRecord);
                $this->refreshCarePlan();
                Session::flash('success', __('Електронний рецепт успішно відхилено.'));
            } else {
                $this->ePrescriptionRequestIdToSign = $requestId;
                $this->openSignatureModal('reject_prescription');
            }
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (EHealthValidationException $e) {
            $e->report();
            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to reject prescription validation: ' . $translatedMsg);
            Session::flash('error', $translatedMsg);
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to reject prescription: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося відхилити рецепт: ') . $e->getMessage());
        }
    }

    public function resendPrescriptionSms(string $prescriptionId): void
    {
        $this->authorizeCarePlanActivityWrite();
        try {
            app(MedicalRequestOwnership::class)
                ->medicationForPerson($prescriptionId, (int) $this->carePlan->personId);

            $response = app(MedicationRequestLifecycleService::class)->resendSms($this->carePlan->person->uuid, $prescriptionId);
            if ($response->successful()) {
                Session::flash('success', __('СМС з кодом погашення успішно надіслано повторно пацієнту.'));
            } else {
                Session::flash('error', __('Не вдалося повторно надіслати СМС: ') . json_encode($response->getData()));
            }
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to resend SMS: ' . $e->getMessage());
            Session::flash('error', __('Помилка надсилання СМС: ') . $e->getMessage());
        }
    }

    public function loadPrintoutForm(string $prescriptionId): string
    {
        try {
            app(MedicalRequestOwnership::class)
                ->medicationForPerson($prescriptionId, (int) $this->carePlan->personId);

            $printout = app(MedicationRequestLifecycleService::class)->fetchPrintoutFromEhealth(
                $this->carePlan->person->uuid,
                $prescriptionId
            );

            if (is_array($printout) && isset($printout['printout_form'])) {
                $printout = $printout['printout_form'];
            }

            if (is_string($printout) && (str_contains($printout, '<html') || str_contains($printout, '<div'))) {
                $this->printableContent = $printout;
                $this->dispatch('printoutLoaded');

                return $this->printableContent;
            }

            $ehealthData = is_array($printout) ? $printout : null;

            $this->printableContent = app(MedicationRequestLifecycleService::class)->buildFallbackPrintoutHtml(
                $this->carePlan,
                $prescriptionId,
                $this->ePrescriptionForm['signature_text'] ?? null,
                $ehealthData,
                Auth::user()?->party?->full_name
            );
            $this->dispatch('printoutLoaded');

            return $this->printableContent;
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return '';
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to load printout form: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося завантажити форму пам’ятки.'));

            return '<h3>' . e(__('Не вдалося сформувати дані для друку. Спробуйте ще раз.')) . '</h3>';
        }
    }

    /**
     * Sign the prescription request with the KEP. Reached only through sign(), which validates the signing fields
     * first.
     *
     * @param  array  $signingData  KNEDP, key container and password validated by sign()
     * @return void
     */
    protected function signEPrescription(array $signingData): void
    {
        if (empty($this->ePrescriptionRequestIdToSign)) {
            Session::flash('error', __('Не вибрано заявку на рецепт для підписання'));
            $this->showSignatureModal = false;

            return;
        }

        $this->authorizeCarePlanActivityWrite();

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->medicationForPerson((string) $this->ePrescriptionRequestIdToSign, (int) $this->carePlan->personId);

            $result = app(MedicationRequestLifecycleService::class)->signPrescription(
                $this->carePlan,
                $requestRecord,
                array_merge($this->form->all(), $signingData, [
                    'request_notification_disabled' => filter_var(
                        $this->ePrescriptionSelectedProgram['settings']['request_notification_disabled'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'medication_unit' => $this->ePrescriptionForm['medication_unit'] ?? 'од.',
                    'signer_tax_id' => Auth::user()?->party?->taxId,
                ]),
                $requestRecord->informWith ?? '',
                $this->ePrescriptionRemainingQty
            );

            if (!empty($result['show_remaining_qty_warning']) || !empty($result['warning_message'])) {
                $this->ePrescriptionShowRemainingQtyWarning = true;
                $this->ePrescriptionRemainingQtyWarningMessage = (string) ($result['warning_message'] ?? '');
            }

            if (!empty($result['warning_message'])) {
                Session::flash('warning', $result['warning_message']);
            }

            Session::flash('success', $result['success_message']);
            $this->showSignatureModal = false;
            $this->ePrescriptionRequestIdToSign = null;
            try {
                $this->refreshCarePlan();
            } catch (\Throwable $exception) {
                Log::warning('Signed prescription page refresh is pending', ['exceptionType' => $exception::class]);
                Session::flash('warning', trim(($result['warning_message'] ?? '')."\n".__('care-plan.eprescription_signed_refresh_pending')));
            }

        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (EHealthValidationException $e) {
            $e->report();
            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to sign E-Prescription validation: ' . $translatedMsg);
            Session::flash('error', $translatedMsg);
            $this->showSignatureModal = false;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to sign E-Prescription: ' . $e->getMessage());
            Session::flash('error', __('Помилка при підписанні рецепту: ') . $e->getMessage());
            $this->showSignatureModal = false;
        }
    }

    /**
     * Sign the rejection of an active prescription with the KEP. Reached only through sign(), which validates the
     * reason and the signing fields first.
     *
     * @param  array  $signingData  KNEDP, key container and password validated by sign()
     * @return void
     */
    protected function signRejectPrescription(array $signingData): void
    {
        if (empty($this->ePrescriptionRequestIdToSign)) {
            Session::flash('error', __('Не вибрано рецепт для відхилення'));
            $this->showSignatureModal = false;

            return;
        }

        $this->authorizeCarePlanActivityWrite();

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->medicationForPerson((string) $this->ePrescriptionRequestIdToSign, (int) $this->carePlan->personId);

            app(MedicationRequestLifecycleService::class)->rejectPrescription(
                $this->carePlan,
                $requestRecord,
                array_merge($this->form->all(), $signingData, ['signer_tax_id' => Auth::user()?->party?->taxId]),
                $this->statusReason
            );

            $this->showSignatureModal = false;
            $this->refreshCarePlan();
            Session::flash('success', __('Електронний рецепт успішно відхилено.'));

        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (EHealthValidationException $e) {
            $e->report();
            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to reject prescription validation: ' . $translatedMsg);
            Session::flash('error', $translatedMsg);
            $this->showSignatureModal = false;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to reject prescription: ' . $e->getMessage());
            $errorMsg = __('Не вдалося відхилити рецепт: ') . $e->getMessage();
            Session::flash('error', $errorMsg);
            $this->showSignatureModal = false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveDrugForActivity(CarePlanActivity $activity): ?array
    {
        if (empty($activity->productReference)) {
            return null;
        }

        $filters = ['innm_dosage_id' => $activity->productReference];
        if (!empty($activity->program)) {
            $filters['medical_program_id'] = $activity->program;
        }

        $data = EHealth::drug()->getMany($filters)->getData();
        if (!empty($data[0])) {
            return $data[0];
        }

        $fallback = EHealth::drug()->getMany(['innm_id' => $activity->productReference])->getData();

        return $fallback[0] ?? null;
    }

    protected function resolveMedicationPackageStep(array $drug): float
    {
        $packages = $drug['packages'] ?? [];
        if (!is_array($packages) || empty($packages)) {
            return 1.0;
        }

        $package = $packages[0];
        $minQty = (float) ($package['package_min_qty'] ?? 0);
        if ($minQty > 0) {
            return $minQty;
        }

        $packageQty = (float) ($package['package_qty'] ?? 0);

        return $packageQty > 0 ? $packageQty : 1.0;
    }

    protected function isMedicationQtyDivisible(float $qty, array $drug): bool
    {
        $step = $this->resolveMedicationPackageStep($drug);
        if ($step <= 0) {
            return true;
        }

        $quotient = $qty / $step;

        return abs($quotient - round($quotient)) < 1e-6;
    }

    /**
     * Show the session message in this response and scroll to the invalid field.
     */
    protected function failEPrescriptionField(string $message, string $field): void
    {
        $this->ePrescriptionWarningMessage = $message;
        $this->addError($field, $message);
        Session::flash('error', $message);
        $this->dispatch('scroll-to-error');
    }

    public function blockPrescription(string $prescriptionId): void
    {
        $this->authorizeCarePlanActivityWrite();

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->medicationForPerson($prescriptionId, (int) $this->carePlan->personId);

            app(MedicationRequestLifecycleService::class)->block($this->carePlan->person->uuid, $prescriptionId, [
                'status_reason' => 'Призупинення або блокування призначення',
            ]);
            MedicalEventsRepository::medicationRequest()->saveStatus($requestRecord, 'blocked');
            $this->refreshCarePlan();
            Session::flash('success', __('Рецепт успішно заблоковано в ЕСОЗ.'));
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to block prescription: ' . $e->getMessage());
            Session::flash('error', __('Помилка блокування рецепту: ') . $e->getMessage());
        }
    }

    public function unblockPrescription(string $prescriptionId): void
    {
        $this->authorizeCarePlanActivityWrite();

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->medicationForPerson($prescriptionId, (int) $this->carePlan->personId);

            app(MedicationRequestLifecycleService::class)->unblock($this->carePlan->person->uuid, $prescriptionId, []);
            MedicalEventsRepository::medicationRequest()->saveStatus($requestRecord, 'active');
            $this->refreshCarePlan();
            Session::flash('success', __('Рецепт успішно розблоковано в ЕСОЗ.'));
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to unblock prescription: ' . $e->getMessage());
            Session::flash('error', __('Помилка розблокування рецепту: ') . $e->getMessage());
        }
    }

    public function checkDispenseHistory(string $prescriptionId): void
    {
        try {
            app(MedicalRequestOwnership::class)
                ->medicationForPerson($prescriptionId, (int) $this->carePlan->personId);

            $dispenses = app(MedicationRequestLifecycleService::class)->getDispenseHistory($this->carePlan->person->uuid, $prescriptionId);
            $items = $dispenses['data'] ?? ($dispenses[0] ?? []);

            if (empty($items) || !is_array($items)) {
                Session::flash('info', __('Погашень рецепту в аптеці наразі не виявлено (рецепт ще не відпущено).'));

                return;
            }

            $count = count($items);
            $latestStatus = $items[0]['status'] ?? __('невідомо');
            Session::flash('success', __('Знайдено :count записів відпуску ліків в аптеці. Останній статус: :status.', ['count' => $count, 'status' => $latestStatus]));
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        } catch (Exception $e) {
            Log::warning('CarePlanShow: check dispense history returned 404 or error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), '404') || str_contains(strtolower($e->getMessage()), 'not found')) {
                Session::flash('info', __('Погашень (відпуску ліків) за цим рецептом в ЕСОЗ наразі не виявлено (аптеки ще не відпускали ліки за цим номером).'));

                return;
            }
            Log::error('CarePlanShow: failed to check dispense history: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося отримати історію погашень: ') . $e->getMessage());
        }
    }

    #[Computed]
    public function referralPriorityOptions(): array
    {
        $name = ($this->referralForm['kind'] ?? '') === 'device_request'
            ? 'device_request_priority'
            : 'SERVICE_REQUEST_PRIORITY';

        return dictionary()->basics()->byName($name)->asCodeDescription()->all();
    }

    public function initReferralForm(int $activityId): void
    {
        $this->authorizeCarePlanActivityWrite();

        try {
            $activity = $this->ownedActivity($activityId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        $activityStatus = strtolower(is_array($activity->status)
            ? ($activity->status['coding'][0]['code'] ?? ($activity->status['text'] ?? ''))
            : (string) $activity->status);

        $blockedActivityStatuses = ['cancelled', 'completed'];

        if ($this->isTerminalCarePlan) {
            Session::flash('error', __('care-plan.cannot_mutate_terminal_care_plan', [
                'status' => CarePlanStatus::labelFor($this->carePlan->status),
            ]));

            return;
        }

        if (in_array($activityStatus, $blockedActivityStatuses)) {
            Session::flash('error', __('Виписування направлення заборонено: це призначення вже завершено або скасовано.'));

            return;
        }

        $resolvedKind = $activity->resolvedKind();
        if (!in_array($resolvedKind, ['service_request', 'device_request'], true)) {
            Session::flash('error', __('care-plan.referral_wrong_activity_kind'));

            return;
        }

        try {
            app(CarePlanActivityEHealthGuard::class)->assertRegisteredInEHealth($this->carePlan, $activity);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return;
        }

        if ($resolvedKind === 'device_request') {
            $this->redirectRoute('persons.device-requests.create', [
                'legalEntity' => legalEntity(),
                'person' => $this->carePlan->person,
                'carePlan' => $this->carePlan->id,
                'activity' => $activity->id,
            ]);

            return;
        }

        $existingDraft = app(ReferralRequestLifecycleService::class)->findDraftByActivity($activity);
        if ($existingDraft) {
            if (app(ReferralRequestLifecycleService::class)->trySyncDraftFromEHealth($this->carePlan, $activity, $existingDraft, $resolvedKind)) {
                if ($activity->status === 'scheduled') {
                    Repository::carePlanActivity()->updateById($activity->id, ['status' => 'in-progress']);
                }
                $this->refreshCarePlan();
                $documentLabel = $resolvedKind === 'device_request'
                    ? __('care-plan.document_type_device_eprescription')
                    : __('care-plan.document_type_service_referral');
                Session::flash('success', __('care-plan.referral_already_in_ehealth_synced', ['document' => $documentLabel]));

                return;
            }

            $this->referralRequestIdToSign = $existingDraft->uuid;
            $documentLabel = $resolvedKind === 'device_request'
                ? __('care-plan.document_type_device_eprescription')
                : __('care-plan.document_type_service_referral');
            Session::flash('info', __('care-plan.referral_unsigned_draft_found', ['document' => $documentLabel]));
        }

        $this->showSignatureModal = false;
        $this->resetValidation();
        $this->referralWarningMessage = '';
        $this->referralDevicePackageQty = 0;

        // Calculate remaining quantity
        $activityQty = (float) ($activity->quantity ?? 0);
        $issuedQty = app(ReferralRequestLifecycleService::class)->sumIssuedQuantity($activity);
        $this->referralRemainingQty = $activity->quantity === null
            ? 1.0
            : max(0.0, $activityQty - $issuedQty);

        $defaultQuantity = min($this->referralRemainingQty, 1.0);
        if ($resolvedKind === 'device_request') {
            $this->referralDevicePackageQty = $this->resolveDevicePackageQuantity($activity);
            if ($this->referralDevicePackageQty <= 0) {
                Session::flash('error', __('care-plan.device_package_qty_unknown'));

                return;
            }

            if ($this->referralRemainingQty < $this->referralDevicePackageQty) {
                Session::flash('error', __('care-plan.device_remaining_below_packaging', [ 'remaining' => $this->referralRemainingQty, 'count' => $this->referralDevicePackageQty, ]));

                return;
            }

            // Default to one package; never fall back to 1 piece when package > 1.
            $defaultQuantity = (float) $this->referralDevicePackageQty;
        }

        $code = $activity->productCodeableConcept ?? $activity->productReference ?? 'од.';

        $activityResource = Repository::carePlanActivity()->getResource($activity);
        $this->referralServiceType = data_get($activityResource, 'detail.productReference.identifier.type.coding.0.code', 'service');
        $this->referralCategoryOptions = [];
        if ($this->referralServiceType === 'service_group') {
            try {
                $group = dictionary()->services()->flattened()->firstWhere('id', $activity->productReference);
                if (($group['type'] ?? '') !== 'service_group' || empty($group['request_allowed'])) {
                    Session::flash('error', __('care-plan.service_group_referral_not_allowed'));

                    return;
                }
                $this->referralCategoryOptions = dictionary()->basics()
                    ->byName('eHealth/SNOMED/service_request_categories')->asCodeDescription()
                    ->except('transfer_of_care')->all(); // Transfers use the encounter discharge flow.
                if ($this->referralCategoryOptions === []) {
                    Session::flash('error', __('care-plan.referral_categories_unavailable'));

                    return;
                }
            } catch (Throwable $exception) {
                report($exception);
                Session::flash('error', __('care-plan.referral_categories_unavailable'));

                return;
            }
        }
        $category = $resolvedKind === 'service_request' && $this->referralServiceType !== 'service_group'
            ? $this->resolveServiceCategory((string) $activity->productReference)
            : null;

        $this->referralServiceCategory = $category ?? ($this->referralServiceType === 'service_group' ? '' : 'procedure');

        $schedule = $activityResource['detail']['scheduledPeriod'] ?? [];
        $draftData = $existingDraft ? MedicalEventsRepository::serviceRequest()->formData($existingDraft) : [];
        if ($this->referralServiceType === 'service_group') {
            $this->referralServiceCategory = $draftData['category'] ?? '';
        }
        $occurrenceDates = $this->resolveReferralOccurrenceDates(
            isset($schedule['start']) ? Carbon::parse($schedule['start'], 'UTC')->setTimezone(config('app.timezone')) : null,
            isset($schedule['end']) ? Carbon::parse($schedule['end'], 'UTC')->setTimezone(config('app.timezone')) : null
        );
        if ($existingDraft) {
            foreach (['started_at' => 'start', 'ended_at' => 'end'] as $field => $boundary) {
                if (!empty($draftData[$field])) {
                    $instant = CarbonImmutable::parse($draftData[$field], 'UTC')->setTimezone(config('app.timezone'));
                    $occurrenceDates[$field] = $instant->format('d.m.Y');
                    $occurrenceDates[$boundary.'_time'] = $instant->format('H:i');
                }
            }
        }

        $reasonReference = [];
        $grounds = collect($this->availableConditions)->concat($this->availableReports)->concat($this->availableObservations)->keyBy('uuid');
        $activity->reasonReferences()->with('type.coding')->get()->each(function ($identifier) use (&$reasonReference, $grounds) {
            $typeCode = $identifier->type->first()?->coding?->first()?->code ?? 'condition';
            $reasonReference[] = [
                'type' => $typeCode,
                'uuid' => $identifier->value,
                'name' => $grounds->get($identifier->value)['name'] ?? $identifier->displayValue ?? __('care-plan.ground_name_unavailable'),
            ];
        });

        $informWith = '';
        $this->referralAuthMethods = [];
        try {
            $authMethods = EHealth::person()->getAuthMethods($this->carePlan->person->uuid)->getData();
            if (is_array($authMethods)) {
                $this->referralAuthMethods = collect($authMethods)->map(static function (array $method): array {
                    $uuid = (string) ($method['id'] ?? $method['uuid'] ?? '');
                    $type = (string) ($method['type'] ?? '');
                    $value = (string) ($method['phone_number'] ?? $method['value'] ?? '');

                    return [
                        'uuid' => $uuid,
                        'label' => trim($type.($value !== '' ? ' · '.$value : '')),
                        'raw' => $uuid !== '' ? "{$uuid}|{$type}|{$value}" : '',
                    ];
                })->filter(static fn (array $m): bool => $m['uuid'] !== '')->values()->all();

                if ($this->referralAuthMethods !== []) {
                    $informWith = $this->referralAuthMethods[0]['raw'];
                }
            }
        } catch (Throwable $e) {
            Log::warning('CarePlanShow: failed to load auth methods for referral: '.$e->getMessage());
        }

        $this->referralForm = [
            'activity_id' => $activity->id,
            'kind' => $resolvedKind,
            'code' => $code,
            'code_label' => $this->resolveReferralServiceLabel($activity),
            'quantity' => $defaultQuantity,
            'started_at' => $occurrenceDates['started_at'],
            'ended_at' => $occurrenceDates['ended_at'],
            'start_time' => $occurrenceDates['start_time'],
            'end_time' => $occurrenceDates['end_time'],
            'priority' => 'routine',
            'intent' => 'order',
            'category' => $this->referralServiceCategory,
            'category_label' => $this->resolveReferralCategoryLabel($this->referralServiceCategory),
            'note' => '',
            'patient_instruction' => '',
            'reason_reference' => $reasonReference,
            'inform_with' => $informWith,
            'program_id' => $activity->program ?? '',
            'supporting_info' => []
        ];

        if ($existingDraft) {
            $this->referralForm = array_replace($this->referralForm, Arr::only($draftData, [
                'quantity', 'priority', 'intent', 'note', 'patient_instruction', 'inform_with', 'supporting_info',
            ]));
        }

        $this->referralShowRemainingQtyWarning = false;
        $this->referralSelectedActivity = $activity->toArray();
        $this->showReferralDrawer = true;
    }

    public function saveReferralDraft(): void
    {
        $this->validateReferral(false);
    }

    public function validateReferral(bool $sign = true): void
    {
        $plan = Repository::carePlan()->findById($this->carePlan->id);
        if (Auth::user()->cannot('manageActivities', $plan)) {
            Session::flash('error', __('care-plan.policy.manage_activities_denied'));

            return;
        }
        if (!$sign && ($this->referralForm['kind'] ?? null) !== 'service_request') {
            Session::flash('error', __('care-plan.referral_wrong_activity_kind'));

            return;
        }

        $this->referralWarningMessage = '';
        $this->referralShowRemainingQtyWarning = false;

        if ($this->referralForm['kind'] === 'service_request' && $this->referralServiceType !== 'service_group') {
            $this->referralForm['category'] = $this->referralServiceCategory ?: ($this->referralForm['category'] ?? 'procedure');
        }

        $rules = [
            'referralForm.started_at' => 'required|date_format:d.m.Y',
            'referralForm.ended_at' => 'required|date_format:d.m.Y',
            'referralForm.start_time' => 'required|date_format:H:i',
            'referralForm.end_time' => 'required|date_format:H:i',
            'referralForm.quantity' => 'required|numeric|min:0.01',
            'referralForm.priority' => ['required', Rule::in(array_keys($this->referralPriorityOptions()))],
        ];

        if ($this->referralForm['kind'] === 'service_request') {
            $rules['referralForm.category'] = $this->referralServiceType === 'service_group'
                ? ['required', 'string', Rule::in(array_keys($this->referralCategoryOptions))]
                : 'required|string';
        }

        $this->validate($rules);
        $period = $this->referralOccurrencePeriod();

        if ($this->referralForm['kind'] === 'service_request') {
            $activityProgram = $this->referralSelectedActivity['program'] ?? null;
            $this->referralForm['program_id'] = !empty($activityProgram) ? $activityProgram : null;
        }

        $qty = (float) $this->referralForm['quantity'];
        if ($qty > $this->referralRemainingQty) {
            $this->referralShowRemainingQtyWarning = true;
            $this->referralWarningMessage = __('Кількість перевищує залишок за призначенням (') . $this->referralRemainingQty . ')';
            $this->flashUserError(__('Кількість перевищує залишок за призначенням.'));

            return;
        }

        if (
            ($this->referralForm['kind'] ?? '') === 'device_request'
            && $this->referralDevicePackageQty <= 0
        ) {
            $message = __('care-plan.device_package_qty_unknown');
            $this->referralWarningMessage = $message;
            $this->flashUserError($message);

            return;
        }

        if (
            ($this->referralForm['kind'] ?? '') === 'device_request'
            && $this->referralDevicePackageQty > 0
            && ((int) $qty % $this->referralDevicePackageQty) !== 0
        ) {
            $message = __('care-plan.device_quantity_packaging', ['count' => $this->referralDevicePackageQty]);
            $this->referralWarningMessage = $message;
            $this->flashUserError($message);

            return;
        }

        // Propose to sign
        $this->showReferralDrawer = false;

        try {
            $activity = $this->ownedActivity((int) $this->referralForm['activity_id']);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }
        if ($sign && $activity) {
            $existingDraft = app(ReferralRequestLifecycleService::class)->findDraftByActivity($activity);
            if ($existingDraft && app(ReferralRequestLifecycleService::class)->trySyncDraftFromEHealth(
                $this->carePlan,
                $activity,
                $existingDraft,
                $this->referralForm['kind']
            )) {
                $this->refreshCarePlan();
                Session::flash('success', __('care-plan.referral_already_in_ehealth_synced', [
                    'document' => __('care-plan.document_type_service_referral'),
                ]));

                return;
            }
        }

        try {
            $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);

            $employeeContext = app(ReferralRequestLifecycleService::class)->resolveEmployeeContext(
                $this->carePlan,
                $activity,
                Auth::user()?->activeDoctorEmployee()?->id
            );

            $this->referralRequestIdToSign = app(ReferralRequestLifecycleService::class)->createCarePlanDraft(
                $this->carePlan,
                array_replace($this->referralForm, $period),
                $qty,
                $employeeContext,
                prequalify: $sign
            );
            if (!$sign) {
                $this->showReferralDrawer = true;
                $this->showSignatureModal = false;
                $this->actionType = '';
                Session::flash('success', __('referrals.draft_saved'));

                return;
            }
            $this->assertReferralOccurrencePeriod($period);
            $signAction = $this->referralForm['kind'] === 'service_request'
                ? 'sign_servicerequest'
                : 'sign_devicerequest';
            $this->openSignatureModal($signAction);
            Session::flash('success', __('care-plan.referral_request_created'));
        } catch (ValidationException $exception) {
            $this->showReferralDrawer = true;
            $this->showSignatureModal = false;
            $this->actionType = '';
            $this->flashUserError($exception->validator->errors()->first());

            throw $exception;
        } catch (EHealthValidationException $exception) {
            $exception->report();
            $this->showReferralDrawer = true;
            $this->flashUserError($exception->getTranslatedMessage());
        } catch (Exception $exception) {
            $this->showReferralDrawer = true;
            Log::error('CarePlanShow: failed to create referral request: ' . $exception->getMessage());
            $kindLabel = ($this->referralForm['kind'] ?? '') === 'device_request'
                ? __('електронний рецепт на медичні вироби')
                : __('заявку на направлення');
            $this->flashUserError(__('Не вдалося створити ').$kindLabel.': '.$exception->getMessage());
        }
    }

    public function resendReferralSms(string $requestId, string $kind): void
    {
        $this->authorizeCarePlanActivityWrite();
        if ($kind === 'device_request') {
            $this->redirectRoute('persons.device-requests', ['legalEntity' => legalEntity(), 'person' => $this->carePlan->person]);

            return;
        }
        try {
            app(MedicalRequestOwnership::class)
                ->referralForPerson($requestId, (int) $this->carePlan->personId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        try {
            $response = app(ReferralRequestLifecycleService::class)->resendSms($this->carePlan->person->uuid, $requestId, $kind);

            if ($response->successful()) {
                Session::flash('success', __('care-plan.referral_sms_resent'));

                return;
            }

            Session::flash('error', __('Не вдалося повторно надіслати СМС: ') . json_encode($response->getData()));
        } catch (EHealthValidationException $exception) {
            Log::error('CarePlanShow: failed to resend referral SMS validation: ' . $exception->getTranslatedMessage());
            Session::flash('error', $exception->getTranslatedMessage());
        } catch (EHealthResponseException $exception) {
            if ($exception->response->status() === 403) {
                Log::warning('CarePlanShow: referral SMS resend forbidden by eHealth ACL', [
                    'request_id' => $requestId,
                    'person_uuid' => $this->carePlan->person->uuid,
                ]);
                Session::flash('warning', __('care-plan.referral_sms_forbidden'));

                return;
            }

            Log::error('CarePlanShow: failed to resend referral SMS response: ' . $exception->getMessage());
            Session::flash('error', __('Помилка надсилання СМС: ') . $exception->getMessage());
        } catch (Exception $exception) {
            Log::error('CarePlanShow: failed to resend referral SMS: ' . $exception->getMessage());
            Session::flash('error', __('Помилка надсилання СМС: ') . $exception->getMessage());
        }
    }

    public function cancelReferral(string $requestId, string $kind): void
    {
        if ($kind === 'device_request') {
            $this->redirectRoute('persons.device-requests', ['legalEntity' => legalEntity(), 'person' => $this->carePlan->person]);

            return;
        }
        $this->openSignatureModal('cancel_referral', null, $requestId);
    }

    public function recallReferral(string $requestId, string $kind): void
    {
        $this->referralExplanatoryLetter = '';
        $this->openSignatureModal('recall_referral', null, $requestId);
    }

    public function loadReferralPrintoutForm(string $requestId): string
    {
        try {
            $html = app(ReferralRequestLifecycleService::class)->buildPrintoutHtml($this->carePlan, $requestId);
            $this->printableContent = $html;

            return $html;
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());

            return '';
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to load referral printout: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося завантажити друковану форму.'));

            return '';
        }
    }

    public function syncReferralFromEHealth(string $requestUuid, string $kind): void
    {
        $this->authorizeCarePlanWrite();

        try {
            $ownership = app(MedicalRequestOwnership::class);
            $requestRecord = match ($kind) {
                'service_request' => $ownership->serviceForPerson($requestUuid, (int) $this->carePlan->personId),
                'device_request' => $ownership->deviceForPerson($requestUuid, (int) $this->carePlan->personId),
                default => throw new ModelNotFoundException(),
            };
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        try {
            $activity = $this->ownedActivityByBasedOnUuid($requestRecord->basedOn?->value);

            $before = [
                'status' => (string) $requestRecord->status,
                'request_number' => (string) ($requestRecord->requestNumber ?? ''),
                'quantity' => (string) $requestRecord->quantity,
                'started_at' => $requestRecord->startedAt?->format('Y-m-d'),
                'ended_at' => $requestRecord->endedAt?->format('Y-m-d'),
            ];

            $dbData = $this->buildReferralSignDbData($requestRecord, $activity);
            app(ReferralRequestLifecycleService::class)->syncReferralFromRemote(
                $this->carePlan,
                $activity,
                $requestRecord,
                $kind,
                $dbData
            );

            ($requestRecord instanceof ServiceRequestRequest ? MedicalEventsRepository::serviceRequest() : MedicalEventsRepository::deviceRequest())->refreshRecord($requestRecord);

            $after = [
                'status' => (string) $requestRecord->status,
                'request_number' => (string) ($requestRecord->requestNumber ?? ''),
                'quantity' => (string) $requestRecord->quantity,
                'started_at' => $requestRecord->startedAt?->format('Y-m-d'),
                'ended_at' => $requestRecord->endedAt?->format('Y-m-d'),
            ];

            Log::info('CarePlanShow: referral synced from eHealth', [
                'request_uuid' => $requestUuid,
                'person_uuid' => $this->carePlan->person->uuid,
                'kind' => $kind,
                'before' => $before,
                'after' => $after,
            ]);

            if ($activity->status === 'scheduled') {
                Repository::carePlanActivity()->updateById($activity->id, ['status' => 'in-progress']);
            }

            $this->refreshCarePlan();

            $changes = [];
            foreach ($before as $field => $value) {
                if (($after[$field] ?? null) !== $value) {
                    $changes[] = match ($field) {
                        'status' => __('статус: ') . $this->resolveReferralStatusLabel($value) . ' → ' . $this->resolveReferralStatusLabel((string) $after[$field]),
                        'request_number' => __('номер: ') . ($value ?: '—') . ' → ' . ($after[$field] ?: '—'),
                        'quantity' => __('кількість: ') . $value . ' → ' . $after[$field],
                        'started_at' => __('початок: ') . ($value ?: '—') . ' → ' . ($after[$field] ?: '—'),
                        'ended_at' => __('кінець: ') . ($value ?: '—') . ' → ' . ($after[$field] ?: '—'),
                        default => $field,
                    };
                }
            }

            Session::flash('success', $changes === [] ? __('care-plan.referral_sync_no_changes') : __('care-plan.referral_sync_updated', ['changes' => implode('; ', $changes)]));
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

        } catch (Exception $exception) {
            Log::error('CarePlanShow: failed to sync referral from eHealth: ' . $exception->getMessage());
            Session::flash('error', __('Не вдалося оновити направлення з ЕСОЗ: ') . $exception->getMessage());
        }
    }

    protected function signReferral(array $signingData): void
    {
        if (empty($this->referralRequestIdToSign)) {
            $this->flashUserError(__('Не вибрано документ для підписання'));
            $this->showSignatureModal = false;

            return;
        }

        $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);

        try {
            $requestRecord = app(MedicalRequestOwnership::class)
                ->referralForPerson((string) $this->referralRequestIdToSign, (int) $this->carePlan->personId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }

        $kind = $requestRecord instanceof ServiceRequestRequest
            ? 'service_request'
            : 'device_request';

        if ($kind === 'device_request') {
            $this->redirectRoute('persons.device-requests.create', ['legalEntity' => legalEntity(), 'person' => $this->carePlan->person, 'draft' => $requestRecord->uuid]);

            return;
        }

        try {
            $activity = $this->ownedActivityByBasedOnUuid($requestRecord->basedOn?->value);

            $employeeContext = $this->resolveReferralEmployeeContext($requestRecord, $activity);

            $uuids = [
                'person_uuid' => $this->carePlan->person->uuid,
                'encounter_uuid' => $this->carePlan->encounter?->uuid ?? null,
                'episode_uuid' => $this->carePlan->episodeUuid(),
                'employee_uuid' => $employeeContext['employee_uuid'],
                'legal_entity_uuid' => $employeeContext['legal_entity_uuid'],
            ];

            $dbData = $this->buildReferralSignDbData($requestRecord, $activity);
            $this->assertReferralOccurrencePeriod($dbData);

            if ($kind === 'service_request') {
                $resource = Fhir::serviceRequest()->toFhir($dbData, $uuids + ['carePlanUuid' => $this->carePlan->uuid, 'resource' => MedicalEventsRepository::serviceRequest()->getResource($requestRecord)]);
                $signPayload = ServiceRequestForm::buildCreateContent($resource);
            } else {
                $signPayload = (new DeviceRequestMapper())->toCreateSignedContent($dbData, $uuids, (string) $this->carePlan->uuid, (string) $activity->uuid);
            }

            $signedContent = signatureService()->signData(
                $signPayload,
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                Auth::user()->party->taxId
            );

            $this->assertReferralOccurrencePeriod($dbData);
            $requestRecord = MedicalEventsRepository::serviceRequest()->storeResource($resource, $this->carePlan->personId, $requestRecord->employeeId, $requestRecord->divisionId);
            $finalResponse = $this->submitClinicalOperation($requestRecord, ['status' => 'active'], fn () => EHealth::serviceRequest()->createSigned($this->carePlan->person->uuid, ['signed_data' => $signedContent, 'signed_data_encoding' => 'base64']));
            if ($finalResponse === null) {
                $this->redirectRoute('care-plans.activities.show', [legalEntity(), $this->carePlan->id, $activity->id], navigate: true);

                return;
            }

            $dbData = app(ReferralRequestLifecycleService::class)->persistAfterSignedCreate(
                $dbData,
                $finalResponse,
                $kind,
                (int) $this->carePlan->personId
            );

            $this->finalizeSignedReferral($dbData, $kind, $activity, alreadyPersisted: true);

        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

        } catch (ValidationException $exception) {
            $this->showSignatureModal = false;
            $this->initReferralForm($activity->id);
            $this->setErrorBag($exception->validator->errors());
            $this->flashUserError($exception->validator->errors()->first());
        } catch (EHealthValidationException $e) {
            if ($e->isDuplicateReferralError()) {
                try {
                    $activity = $this->ownedActivityByBasedOnUuid($requestRecord->basedOn?->value);

                    $dbData = $this->buildReferralSignDbData($requestRecord, $activity);
                    $dbData = app(ReferralRequestLifecycleService::class)->syncReferralFromRemote(
                        $this->carePlan,
                        $activity,
                        $requestRecord,
                        $kind,
                        $dbData
                    );
                    $this->finalizeSignedReferral($dbData, $kind, $activity, alreadyPersisted: true, alreadyExists: true);
                } catch (Exception $syncException) {
                    Log::error('CarePlanShow: failed to sync referral after duplicate eHealth id: ' . $syncException->getMessage());
                    $this->flashUserError(__('Документ вже існує в ЕСОЗ, але не вдалося синхронізувати локальні дані: ') . $syncException->getMessage());
                    $this->showSignatureModal = false;
                }

                return;
            }

            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to sign referral validation: ' . $translatedMsg);
            $this->flashUserError($translatedMsg);
            $this->showSignatureModal = false;
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan referral');
            $this->showSignatureModal = false;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to sign referral: ' . $e->getMessage());
            $this->flashUserError(__('Не вдалося підписати документ: ') . $e->getMessage());
            $this->showSignatureModal = false;
        }
    }

    protected function signRecallReferral(array $signingData): void
    {
        if (empty($this->referralRequestIdToSign)) {
            Session::flash('error', __('Не вибрано направлення для відкликання'));
            $this->showSignatureModal = false;

            return;
        }

        $letter = trim((string) $this->referralExplanatoryLetter);
        if ($letter === '') {
            $this->addError('referralExplanatoryLetter', __('care-plan.referral_recall_letter_required'));

            return;
        }

        try {
            $record = app(MedicalRequestOwnership::class)
                ->referralForPerson((string) $this->referralRequestIdToSign, (int) $this->carePlan->personId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }
        $service = $record instanceof ServiceRequestRequest ? $record : null;
        $device = $service ? null : $record;

        $record = $service ?: $device;

        if (!$service) {
            Session::flash('error', __('care-plan.referral_recall_service_only'));
            $this->showSignatureModal = false;

            return;
        }

        $this->validate(['statusReason' => ['required', 'string']]);
        try {
            $api = EHealth::serviceRequest();
            $snapshot = $this->fetchServiceRequestSnapshot($this->carePlan->person->uuid, $record->uuid);
            $record = MedicalEventsRepository::serviceRequest()->storeResource((new ServiceRequestMapper())->normalize($snapshot), (int) $this->carePlan->personId, $record->employeeId, $record->divisionId);
            $payload = ServiceRequestForm::buildStatusContent($snapshot, $this->statusReason, $letter, 'eHealth/service_request_recall_reasons');

            $signedContent = signatureService()->signData(
                $payload,
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                Auth::user()->party->taxId
            );

            $finalResponse = $this->submitClinicalOperation($record, ['status' => 'recalled', 'statusReason' => $payload['status_reason'], 'explanatoryLetter' => $letter], fn () => EHealth::serviceRequest()->recall($this->carePlan->person->uuid, $record->uuid, ['signed_data' => $signedContent, 'signed_data_encoding' => 'base64']), 'service_request:recall');
            if ($finalResponse === null) {
                return;
            }

            $this->persistReferralStatusFromJob(
                $finalResponse,
                $record,
                ServiceRequestStatus::RECALLED
            );
            $this->showSignatureModal = false;
            $this->referralExplanatoryLetter = '';
            $this->refreshCarePlan();
            Session::flash('success', __('care-plan.referral_recall_success'));
        } catch (EHealthValidationException $e) {
            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to recall referral validation: '.$translatedMsg);
            Session::flash('error', $translatedMsg);
            $this->showSignatureModal = false;
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan referral recall');
            $this->showSignatureModal = false;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to recall referral: '.$e->getMessage());
            Session::flash('error', __('Не вдалося відкликати направлення: ').$e->getMessage());
            $this->showSignatureModal = false;
        }
    }

    protected function signCancelReferral(array $signingData): void
    {
        if (empty($this->referralRequestIdToSign)) {
            Session::flash('error', __('Не вибрано направлення для скасування'));
            $this->showSignatureModal = false;

            return;
        }

        try {
            $record = app(MedicalRequestOwnership::class)
                ->referralForPerson((string) $this->referralRequestIdToSign, (int) $this->carePlan->personId);
        } catch (ModelNotFoundException) {
            Session::flash('error', __('care-plan.document_context_unavailable'));
            $this->showSignatureModal = false;
            $this->actionType = '';

            return;
        }
        $service = $record instanceof ServiceRequestRequest ? $record : null;
        $device = $service ? null : $record;

        $record = $service ?: $device;

        $kind = $service ? 'service_request' : 'device_request';
        if ($service) {
            $this->validate(['statusReason' => ['required', 'string'], 'referralExplanatoryLetter' => ['required', 'string']]);
        }

        if ($kind === 'device_request') {
            $this->redirectRoute('persons.device-requests', ['legalEntity' => legalEntity(), 'person' => $this->carePlan->person]);

            return;
        }

        try {
            $payload = [
                'status_reason' => $this->statusReason ?: 'entered-in-error'
            ];

            if ($kind === 'service_request') {
                $api = EHealth::serviceRequest();
                $snapshot = $this->fetchServiceRequestSnapshot($this->carePlan->person->uuid, $record->uuid);
                $record = MedicalEventsRepository::serviceRequest()->storeResource((new ServiceRequestMapper())->normalize($snapshot), (int) $this->carePlan->personId, $record->employeeId, $record->divisionId);
                $payload = ServiceRequestForm::buildStatusContent($snapshot, $this->statusReason, trim($this->referralExplanatoryLetter), 'eHealth/service_request_cancel_reasons');
            }

            $signedContent = signatureService()->signData(
                $kind === 'service_request' ? $payload : Arr::toSnakeCase($payload),
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                Auth::user()->party->taxId
            );

            $finalResponse = $this->submitClinicalOperation($record, ['status' => 'entered-in-error', 'statusReason' => $payload['status_reason'], 'explanatoryLetter' => trim($this->referralExplanatoryLetter)], fn () => EHealth::serviceRequest()->cancel($this->carePlan->person->uuid, $record->uuid, ['signed_data' => $signedContent, 'signed_data_encoding' => 'base64']), 'service_request:cancel');
            if ($finalResponse === null) {
                return;
            }

            $this->persistReferralStatusFromJob(
                $finalResponse,
                $record,
                ServiceRequestStatus::ENTERED_IN_ERROR
            );
            $this->showSignatureModal = false;
            $this->refreshCarePlan();
            Session::flash('success', __('care-plan.referral_cancel_success'));
        } catch (EHealthValidationException $e) {
            $translatedMsg = $e->getTranslatedMessage();
            Log::error('CarePlanShow: failed to cancel referral validation: ' . $translatedMsg);
            Session::flash('error', $translatedMsg);
            $this->showSignatureModal = false;
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan referral cancellation');
            $this->showSignatureModal = false;
        } catch (Exception $e) {
            Log::error('CarePlanShow: failed to cancel referral: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося скасувати направлення: ') . $e->getMessage());
            $this->showSignatureModal = false;
        }
    }

    protected function resolveReferralServiceLabel(CarePlanActivity $activity): string
    {
        $display = $activity->productReferenceIdentifier?->displayValue;
        if (filled($display)) {
            return $display;
        }
        try {
            $service = $this->findServiceInCatalog(dictionary()->services()->all(), (string) $activity->productReference);
            if (filled($service['name'] ?? null)) {
                return trim(($service['code'] ?? '').' '.$service['name']);
            }
        } catch (Throwable $exception) {
            Log::warning('Care plan service label unavailable', ['exceptionType' => $exception::class]);
        }

        return __('care-plan.service_name_unavailable');
    }

    protected function resolveServiceCategory(string $serviceId): ?string
    {
        try {
            $catalog = dictionary()->services()->all();

            if (!is_array($catalog)) {
                return null;
            }

            $category = $this->findServiceCategoryInCatalog($catalog, $serviceId);

            return $category !== null ? (string) $category : null;
        } catch (Exception $exception) {
            Log::warning('CarePlanShow: failed to resolve service category: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * @param  array<mixed>  $nodes
     */
    protected function findServiceCategoryInCatalog(array $nodes, string $serviceId): ?string
    {
        return $this->findServiceInCatalog($nodes, $serviceId)['category'] ?? null;
    }

    protected function findServiceInCatalog(array $nodes, string $serviceId): ?array
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['id'] ?? null) === $serviceId) {
                return $node;
            }

            foreach (['services', 'groups'] as $childKey) {
                if (!empty($node[$childKey]) && is_array($node[$childKey])) {
                    $service = $this->findServiceInCatalog($node[$childKey], $serviceId);
                    if ($service !== null) {
                        return $service;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{started_at: string, ended_at: string}
     */
    protected function resolveReferralOccurrenceDates(?Carbon $scheduledStart, ?Carbon $scheduledEnd): array
    {
        $minStart = now()->addMinutes(30)->startOfMinute();
        $start = $scheduledStart && $scheduledStart->greaterThan($minStart)
            ? $scheduledStart->copy()
            : $minStart->copy();
        $end = $scheduledEnd?->copy() ?? $start->copy()->addMonths(3);

        return [
            'started_at' => $start->format('d.m.Y'),
            'ended_at' => $end->format('d.m.Y'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
        ];
    }

    protected function referralOccurrencePeriod(): array
    {
        $start = CarbonImmutable::createFromFormat(
            '!d.m.Y H:i',
            $this->referralForm['started_at'].' '.$this->referralForm['start_time'],
            config('app.timezone')
        );
        $end = CarbonImmutable::createFromFormat(
            '!d.m.Y H:i',
            $this->referralForm['ended_at'].' '.$this->referralForm['end_time'],
            config('app.timezone')
        );
        foreach (['start_time' => $start, 'end_time' => $end] as $field => $date) {
            if ($date->format('H:i') !== $this->referralForm[$field]) {
                throw ValidationException::withMessages([
                    'referralForm.'.$field => __('referrals.period_fields.nonexistent_time'),
                ]);
            }
        }
        $period = ['started_at' => $start->toIso8601String(), 'ended_at' => $end->toIso8601String()];
        $this->assertReferralOccurrencePeriod($period);

        return $period;
    }

    protected function assertReferralOccurrencePeriod(array $period): void
    {
        $serverNow = CarbonImmutable::now();
        $rules = [
            'referralForm.start_time' => ['required', 'date', 'after_or_equal:'.$serverNow->toIso8601String()],
            'referralForm.ended_at' => ['required', 'date', 'after_or_equal:referralForm.start_time'],
        ];
        if (array_key_exists('priority', $period)) {
            $rules['referralForm.priority'] = ['required', Rule::in(array_keys($this->referralPriorityOptions()))];
        }
        $validator = Validator::make(['referralForm' => [
            'start_time' => $period['started_at'] ?? null,
            'ended_at' => $period['ended_at'] ?? null,
            'priority' => $period['priority'] ?? null,
        ]], $rules, [
            'referralForm.start_time.after_or_equal' => __('errors.ehealth.messages.occurrence_start_in_past').' '.__('referrals.period_fields.comparison_context', [
                'start' => $period['started_at'] ?? '',
                'now' => $serverNow->toIso8601String(),
            ]),
            'referralForm.ended_at.after_or_equal' => __('errors.ehealth.messages.period_end_before_start'),
        ]);
        if ($validator->fails() && isset($validator->failed()['referralForm.start_time']['AfterOrEqual'])) {
            Log::notice('Care plan referral start is in the past', [
                'draft_uuid' => $this->referralRequestIdToSign,
                'start' => $period['started_at'] ?? null,
                'end' => $period['ended_at'] ?? null,
                'server_now' => $serverNow->toIso8601String(),
                'timezone' => config('app.timezone'),
            ]);
        }
        $validator->validate();
    }

    /**
     * Resolve device packaging_count from eHealth device definition (package step for eRx).
     */
    protected function resolveDevicePackageQuantity(CarePlanActivity $activity): int
    {
        $reference = (string) ($activity->productReference ?? '');
        if ($reference === '') {
            return 0;
        }

        try {
            $device = $this->findDeviceDefinitionByReference($reference, $activity->program);

            if (!is_array($device)) {
                return 0;
            }

            $packaging = $device['packaging'] ?? null;
            $count = is_array($packaging)
                ? (int) ($packaging['packaging_count'] ?? $packaging['packagingCount'] ?? 0)
                : 0;

            return max(0, $count);
        } catch (Throwable $exception) {
            Log::warning('CarePlanShow: failed to resolve device package quantity: '.$exception->getMessage());

            return 0;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDeviceDefinitionByReference(string $reference, ?string $programId): ?array
    {
        try {
            $data = EHealth::deviceDefinition()->getById($reference)->getData();
            if (is_array($data) && (isset($data['id']) || isset($data['uuid']))) {
                return $data;
            }
            if (isset($data[0]) && is_array($data[0])) {
                return $data[0];
            }
        } catch (Throwable $exception) {
            Log::debug('CarePlanShow: device definition getById failed: '.$exception->getMessage(), [
                'uuid' => $reference,
            ]);
        }

        $filters = ['page_size' => 100];
        if (!empty($programId)) {
            $filters['medical_program_id'] = $programId;
        }

        $page = 1;
        $maxPages = 10;

        do {
            $filters['page'] = $page;
            $response = EHealth::deviceDefinition()->getMany($filters);
            $device = collect($response->getData())->first(
                static fn (array $item): bool => (string) ($item['id'] ?? $item['uuid'] ?? '') === $reference
            );

            if (is_array($device)) {
                return $device;
            }

            $paging = $response->getPaging();
            $totalPages = (int) ($paging['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages && $page <= $maxPages);

        return null;
    }

    protected function flashUserError(string $message): void
    {
        Session::flash('error', $message);
    }

    protected function flashUserSuccess(string $message): void
    {
        Session::flash('success', $message);
    }

    /**
     * @param  array<string, mixed>  $finalResponse
     */
    protected function persistReferralStatusFromJob(
        array $finalResponse,
        ServiceRequestRequest|DeviceRequestRequest $record,
        ServiceRequestStatus $fallback
    ): void {
        $result = $finalResponse['result'] ?? null;
        $entity = is_array($result) ? ($result[0] ?? $result) : $finalResponse;
        $raw = is_array($entity) ? ($entity['status'] ?? null) : null;
        $resolved = is_string($raw) ? ServiceRequestStatus::resolve($raw) : null;

        if ($record instanceof ServiceRequestRequest) {
            MedicalEventsRepository::serviceRequest()->updateClinicalStatus($record, ($resolved ?? $fallback)->value);
        } else {
            MedicalEventsRepository::deviceRequest()->saveStatus($record, ($resolved ?? $fallback)->value);
        }
    }

    /**
     * @return array{
     *     employee_id: int|null,
     *     division_id: int|null,
     *     employee_uuid: string|null,
     *     legal_entity_uuid: string|null
     * }
     */
    protected function resolveReferralEmployeeContext(
        ServiceRequestRequest|DeviceRequestRequest $requestRecord,
        CarePlanActivity $activity
    ): array {
        $context = app(ReferralRequestLifecycleService::class)->resolveEmployeeContext(
            $this->carePlan,
            $activity,
            $requestRecord->employeeId
        );

        return [
            'employee_id' => $requestRecord->employeeId ?? $context['employee_id'],
            'division_id' => $requestRecord->divisionId ?? $context['division_id'],
            'employee_uuid' => $context['employee_uuid'],
            'legal_entity_uuid' => $context['legal_entity_uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReferralSignDbData(
        ServiceRequestRequest|DeviceRequestRequest $requestRecord,
        CarePlanActivity $activity
    ): array {
        $employeeContext = $this->resolveReferralEmployeeContext($requestRecord, $activity);

        return app(ReferralRequestLifecycleService::class)->buildSignDbData(
            $requestRecord,
            $activity,
            $this->carePlan,
            $employeeContext
        );
    }

    /**
     * @param  array<string, mixed>  $dbData
     */
    protected function finalizeSignedReferral(array $dbData, string $kind, CarePlanActivity $activity, bool $alreadyPersisted = false, bool $alreadyExists = false): void
    {
        if (!$alreadyPersisted) {
            if ($kind === 'service_request') {
                app(ReferralRequestLifecycleService::class)->saveServiceRequest($dbData, $this->carePlan->personId);
            } else {
                MedicalEventsRepository::deviceRequest()->store($dbData, $this->carePlan->person_id);
            }
        }

        if ($activity->status === 'scheduled') {
            Repository::carePlanActivity()->updateById($activity->id, ['status' => 'in-progress']);
        }

        $this->showSignatureModal = false;
        $documentLabel = $kind === 'device_request'
            ? __('Електронний рецепт на медичні вироби')
            : __('Електронне направлення');
        $finalStatusCode = strtolower((string) ($dbData['status'] ?? ''));
        if (in_array($finalStatusCode, ['pending', 'processing'], true)) {

            Session::flash('warning', $documentLabel.__(' прийнято в обробку ЕСОЗ. Фінальний статус з’явиться після завершення асинхронної задачі.'));
        } elseif ($alreadyExists) {
            $this->flashUserSuccess($documentLabel.__(' вже існував у ЕСОЗ. Локальні дані синхронізовано.'));
        } else {
            $message = __('care-plan.referral_submission_confirmed');
            if (filled($dbData['request_number'] ?? null)) {
                $message .= ' '.__('care-plan.document_number', ['number' => $dbData['request_number']]);
            }
            $this->flashUserSuccess($message);
        }
        $this->refreshCarePlan();
    }

    protected function resolveReferralCategoryLabel(string $category): string
    {
        $key = 'care-plan.referral_category.' . $category;

        return Lang::has($key) ? __($key) : $category;
    }
}
