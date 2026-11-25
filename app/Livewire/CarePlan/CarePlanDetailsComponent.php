<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Classes\eHealth\Api\Approval as ApprovalApi;
use App\Classes\eHealth\Api\CarePlan as CarePlanApi;
use App\Classes\eHealth\EHealth;
use App\Core\Arr;
use App\Core\BaseForm;
use App\Enums\CarePlanStatus;
use App\Enums\JobStatus;
use App\Enums\MedicalProgram\Type;
use App\Enums\Person\ServiceRequestStatus;
use App\Enums\User\Role;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthJobTimeoutException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Exceptions\Signature\SignatureException;
use App\Jobs\CarePlanFullSync;
use App\Jobs\RemoteEHealthLinksProcessing;
use App\Livewire\CarePlan\Concerns\HandlesCarePlanApprovalSms;
use App\Livewire\CarePlan\Forms\CarePlanActivityForm;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Livewire\Concerns\ReadsClinicalSnapshots;
use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Repositories\CarePlanActivityRepository;
use App\Repositories\CarePlanRepository;
use App\Repositories\Repository;
use App\Services\Dictionary\DictionaryManager;
use App\Services\Dictionary\MedicationProgramPicker;
use App\Services\MedicalEvents\CarePlanActivityValidationService;
use App\Services\MedicalEvents\CarePlanApprovalService;
use App\Services\MedicalEvents\CarePlanLifecycleGateService;
use App\Services\MedicalEvents\DeviceProgramParticipationGuard;
use App\Services\MedicalEvents\FhirResource;
use App\Services\MedicalEvents\MedicalRequestOwnership;
use App\Services\MedicalEvents\PatientApprovalService;
use App\Traits\InteractsWithApprovals;
use App\Dto\CarePlan\Ehealth as CarePlanEhealthData;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use ArrayObject;
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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;
use Throwable;

abstract class CarePlanDetailsComponent extends Component
{
    use InteractsWithApprovals;
    use HandlesCarePlanApprovalSms;
    use ReadsClinicalSnapshots;

    // Everything the server derives is #[Locked]: it is sent to the browser on every request
    // and would otherwise come back whatever the client made of it. Only properties bound with
    // wire:model or entangled with Alpine stay writable.

    #[Locked]
    public CarePlan $carePlan;

    #[Locked]
    public array $planSubmission = [];

    #[Locked]
    public ?string $inpatientApprovalStatus = null;

    #[Locked]
    public bool $activateAfterSubmission = false;

    public bool $showActivationPrompt = false;

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
            abort_unless(Auth::user()?->can('view', $this->carePlan), 404);
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
        if (Auth::user()?->can('manageActivities', $plan) !== true) {
            $message = __('care-plan.prescribing_patient_access_required');
            Session::flash('error', $message);

            throw ValidationException::withMessages(['carePlan' => $message]);
        }
    }

    public function getCanManageActivitiesProperty(): bool
    {
        return !$this->isTerminalCarePlan && !$this->carePlan->requiresFhirImport()
            && Auth::user()?->can('manageActivities', $this->carePlan) === true;
    }

    protected function authorizeCarePlanWrite(): void
    {
        if (Auth::user()?->can('manage', $this->carePlan) !== true) {
            $message = __('care-plan.no_permission_update');
            Session::flash('error', $message);

            throw ValidationException::withMessages(['carePlan' => $message]);
        }
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
        abort_unless(Auth::user()?->can('view', $carePlan), 404);

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

        $user = Auth::user();
        if ($action === 'sign_plan' && $this->canSignPlan
            && $user?->can('update', $this->carePlan) && $user->can('create', CarePlan::class)
            && method_exists($this, 'sign')) {
            $this->openSignatureModal($action);
        }
    }

    protected function submitClinicalOperation(
        CarePlan|CarePlanActivity|ServiceRequestRequest $record,
        array $changes,
        callable $send,
        ?string $requiredScope = null
    ): ?array {
        $result = RemoteEHealthLinksProcessing::submitOperation($record, $changes, $send, $requiredScope);
        if ($result === null) {
            $this->showSignatureModal = false;
        }

        return $result;
    }

    protected function signPlan(CarePlanRepository $repository, array $signingData): void
    {
        $user = Auth::user();
        if ($user?->can('update', $this->carePlan) !== true || $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return;
        }

        try {
            $repository->assertDraftSigningReady($this->carePlan);
            $resource = $repository->getResource($this->carePlan->id);
            $content = objectMapper()->map(new ArrayObject($resource), CarePlanEhealthData::class);
            $signedContent = signatureService()->signData(
                $this->signingPayload($content),
                $signingData['knedp'],
                $signingData['keyContainer'],
                $signingData['password'],
                $user->party->taxId
            );
            $result = RemoteEHealthLinksProcessing::submitOperation(
                $this->carePlan,
                ['status' => CarePlanStatus::PENDING->value],
                fn () => EHealth::carePlan()->create($this->carePlan->person->uuid, [
                    'signed_data' => $signedContent,
                    'signed_data_encoding' => 'base64',
                ], $this->carePlan->uuid),
                operationContext: ['expected' => $resource]
            );
            if ($result === null) {
                $this->showSignatureModal = false;
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
                $exception instanceof ClinicalStorageException => $exception->getMessage(),
                $exception instanceof EHealthConnectionException => __('care-plan.connection_error'),
                default => __('care-plan.unexpected_error'),
            });
            $this->planSubmission = $repository->submissionState($this->carePlan);
            $this->showSignatureModal = false;
        }
    }

    /** Return the native, validated array passed to the signature provider. */
    protected function signingPayload(CarePlanEhealthData $content): array
    {
        $payload = $this->omitEmptyFields(Arr::toSnakeCase(get_object_vars($content)));
        Validator::make($payload, [
            'id' => 'required|uuid',
            'title' => 'required|string',
            'intent' => 'required|in:order',
            'status' => 'required|in:new',
            'category.coding' => 'required|array|min:1',
            'period.start' => 'required|date',
            'period.end' => 'sometimes|nullable|date|after_or_equal:period.start',
            'encounter.identifier.value' => 'required|uuid',
            'author.identifier.value' => 'required|uuid',
            'addresses' => 'required|array|min:1',
            'terms_of_service.coding' => 'required|array|min:1',
            'based_on' => 'sometimes|nullable|array',
            'part_of' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string',
            'note' => 'sometimes|nullable|string',
            'supporting_info' => 'sometimes|nullable|array',
            'supporting_info.*.identifier.value' => 'required|uuid',
            'supporting_info.*.identifier.type.coding' => 'required|array|min:1',
            'supporting_info.*.identifier.type.coding.*.code' => 'required|in:episode_of_care,procedure,diagnostic_report',
            'contributor' => 'sometimes|nullable|array',
            'contributor.*.identifier.value' => 'required|uuid',
            'inform_with' => 'sometimes|nullable|array',
            'inform_with.auth_method_id' => 'sometimes|nullable|uuid',
        ])->validate();

        return $payload;
    }

    /** Optional empty fields are omitted and JSON lists keep consecutive indexes. */
    protected function omitEmptyFields(array $data): array
    {
        $data = array_map(fn (mixed $value): mixed => is_array($value) ? $this->omitEmptyFields($value) : $value, $data);
        $filtered = array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return array_is_list($data) ? array_values($filtered) : $filtered;
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

            $finalResponse = $this->submitClinicalOperation($this->carePlan, ['status' => CarePlanStatus::CANCELLED->value, 'statusReason' => $statusReasonCodeableConcept], fn () => app(CarePlanApi::class)->cancel(
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

    public function completePlan(CarePlanRepository $repository): void
    {
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
            $finalResponse = $this->submitClinicalOperation($this->carePlan, ['status' => CarePlanStatus::COMPLETED->value, 'statusReason' => $statusReasonCodeableConcept], fn () => app(CarePlanApi::class)->complete(
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
        if (Auth::user()?->can('view', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.sync_access_denied'));

            return false;
        }

        try {
            $this->synchronizePlanResource();
            app(CarePlanApprovalService::class)->syncForCarePlan($this->carePlan);
            $this->refreshCarePlan();
            $this->dispatch('refreshApprovals');

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    protected function synchronizePlanResource(): void
    {
        $patientId = $this->carePlan->person->uuid;
        $planId = $this->carePlan->uuid;
        $details = EHealth::carePlan()->getDetails($patientId, $planId)->validate();
        $plan = objectMapper()->map($details, CarePlanData::class);
        $plan->prepareResponse($details);
        $activities = EHealth::carePlanActivity()->allDetails($patientId, $planId);
        Repository::carePlan()->storeAggregate($plan, $activities, $this->carePlan->personId, $this->carePlan->legalEntityId);
    }

    public function render()
    {
        return $this->renderCarePlan();
    }

    abstract protected function renderCarePlan();
}
