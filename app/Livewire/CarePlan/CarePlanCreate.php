<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Classes\eHealth\Api\Approval as ApprovalApi;
use App\Classes\eHealth\EHealth;
use App\Core\Arr;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Enums\CarePlanStatus;
use App\Enums\CarePlanTermsOfService;
use App\Enums\Episode\Status as EpisodeStatus;
use App\Enums\Person\AuthenticationMethod;
use App\Enums\Person\ProcedureStatus;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Livewire\CarePlan\Forms\PatientSearchForm;
use App\Livewire\Person\Records\BasePatientComponent;
use App\Models\CarePlan;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\DiagnosticReport;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\Episode;
use App\Models\MedicalEvents\Sql\Procedure;
use App\Models\Person\Person;
use App\Models\User;
use App\Repositories\MedicalEvents\Repository as MedicalEventsRepository;
use App\Repositories\Repository;
use App\Services\MedicalEvents\CarePlanApprovalService;
use App\Services\MedicalEvents\MedicalRequestOwnership;
use App\Traits\InteractsWithApprovals;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;
use Throwable;

class CarePlanCreate extends BasePatientComponent
{

    use InteractsWithApprovals;

    public bool $showSignatureModal = false;
    public bool $showMethodSelectionModal = false;
    public string $patientUuid = '';
    #[Locked]
    public string $conditionUuid = '';
    public string $medicalRecordType = 'CONDITION';
    #[Locked]
    public ?string $carePlanUuid = null;

    #[Locked]
    public ?string $draftUuid = null;

    #[Locked]
    public ?int $draftLegalEntityId = null;

    #[Locked]
    public ?int $draftOpenedById = null;

    public CarePlanForm $form;

    public PatientSearchForm $patientSearch;

    public array $categories = [];

    #[Locked]
    public array $allowedCategoryCodes = [];
    public array $diagnoses = [];
    #[Locked]
    public array $authMethods = [];
    public array $patientSearchResults = [];
    #[Locked]
    public bool $allowsPatientChange = false;
    public bool $showAdditionalSearchParams = false;
    public ?array $dictionaries = [];
    public array $doctors = [];

    /**
     * Dictionaries to load via FormTrait::getDictionary().
     */
    protected array $dictionaryNames = [
        'eHealth/care_plan_categories',
        'eHealth/encounter_classes',
        'PROVIDING_CONDITION',
    ];

    /**
     * Available encounters that have been confirmed by eHealth (for the encounter selector).
     */
    public array $availableEncounters = [];
    public array $availableEpisodes = [];

    public function mount(LegalEntity $legalEntity, $personId = null, $encounter = null, ?Person $person = null): void
    {
        $draftCreation = $this->isDraftCreation();
        if ($draftCreation) {
            $user = Auth::user();
            abort_unless($user?->can('create', CarePlan::class), 404);
            $this->draftUuid = Str::uuid()->toString();
            $this->draftLegalEntityId = $legalEntity->id;
            $this->draftOpenedById = $user->id;
        }

        $routePersonId = $person?->id ?? request()->route('personId');
        $personId ??= $routePersonId;
        $encounterRouteParam = request()->route('encounter') ?? (request()->query('encounter') ?? request()->query('encounterId'));
        $this->allowsPatientChange = $draftCreation && empty($routePersonId) && empty($encounterRouteParam);

        $resolvedPersonId = null;
        $resolvedEncounter = null;

        // Try to resolve encounter from route parameters, query string or sequence-passed personId

        if ($encounterRouteParam) {
            if (Str::isUuid((string) $encounterRouteParam)) {
                $resolvedEncounter = Repository::carePlan()->findEncounter($encounterRouteParam);
            } elseif (is_numeric($encounterRouteParam)) {
                $resolvedEncounter = Repository::carePlan()->findEncounter((int) $encounterRouteParam);
            }
            if ($resolvedEncounter) {
                $resolvedPersonId = $resolvedEncounter->person_id;
            }
        }

        if ($person !== null && $encounterRouteParam) {
            abort_unless($resolvedEncounter !== null && (int) $resolvedEncounter->person_id === $person->id, 404);
            abort_unless(Auth::user()?->can('view', [$resolvedEncounter, $person]), 404);
        }

        // If personId was passed, use it, unless it was sequential mapping of encounter
        if (!$resolvedPersonId && $personId && (!ctype_digit((string) $personId) || !Repository::carePlan()->findPatient((int) $personId))) {
            // Check if $personId is actually an encounter ID or UUID
            $possibleEncounter = null;
            if (Str::isUuid((string) $personId)) {
                $possibleEncounter = Repository::carePlan()->findEncounter($personId);
            } elseif (is_numeric($personId)) {
                $possibleEncounter = Repository::carePlan()->findEncounter((int) $personId);
            }

            if ($possibleEncounter) {
                $resolvedEncounter = $possibleEncounter;
                $resolvedPersonId = $possibleEncounter->person_id;
            } else {
                $resolvedPersonId = ctype_digit((string) $personId) ? (int) $personId : null;
            }
        }

        $patientId = $resolvedPersonId ?? $personId;
        $this->personId = ctype_digit((string) $patientId) ? (int) $patientId : 0;

        if ($this->personId > 0) {
            parent::mount($legalEntity);
        } else {
            $this->patientFullName = __('care-plan.new_care_plan');
            $this->verificationStatus = '';
            $this->uuid = '';
            $this->declarationNumber = null;
        }

        $person = Repository::carePlan()->findPatient($this->personId);
        if ($person) {
            $name = $person->primary_name;
            $this->form->patient = $name ? trim($name->last_name . ' ' . $name->first_name . ' ' . ($name->second_name ?? '')) : '';

            if (!$draftCreation) {
                $this->availableEpisodes = Repository::carePlan()->activeEpisodes($person);
            }
        }

        $this->form->medicalNumber = Repository::carePlan()->nextDisplayNumber();
        $this->loadAvailableEncounters();

        $conditionUuid = request()->query('conditionUuid');
        $this->conditionUuid = is_string($conditionUuid) && Str::isUuid($conditionUuid) ? $conditionUuid : '';

        if ($resolvedEncounter) {
            $this->form->encounter = $resolvedEncounter->uuid;
            if ($draftCreation) {
                $resolvedEncounter = $person ? $this->draftEncounter($person) : null;
                abort_if($resolvedEncounter === null, 404);
            }

            $this->applyEncounterToForm($resolvedEncounter);
        }

        $this->form->periodStart = now()->format(config('app.date_format'));
        $this->form->periodStartTime = now()->format('H:i');

        $this->synchronizeSystemFields();
        $this->loadDoctorsAndDictionaries();
        $this->refreshCategoryOptions();
    }

    /**
     * Re-resolve the care plan writer employee for the currently selected terms_of_service and
     * refresh the read-only "Автор" display field. eHealth ties the author to a specific
     * healthcare_service.providing_condition, so the effective author can change when the user
     * switches "умови надання послуг" (terms_of_service).
     */
    public function updatedFormTermsOfService(): void
    {
        $this->synchronizeSystemFields();
    }

    protected function synchronizeSystemFields(): void
    {
        $this->form->intent = 'order';
        $this->form->termsOfService = Repository::carePlan()->encounterTermsOfService(
            $this->form->encounter,
            (int) $this->personId
        );
        $this->refreshAuthorDisplay();
    }

    protected function refreshCategoryOptions(): void
    {
        if (!$this->isDraftCreation()) {
            $this->resolveEncounterData();
        }
        $this->allowedCategoryCodes = Repository::carePlan()->categoryCodesForEncounter($this->form->encounter, (int) $this->personId);
        $this->resetValidation('form.category');
        if (!in_array($this->form->category, $this->allowedCategoryCodes, true)) {
            $this->form->category = count($this->allowedCategoryCodes) === 1 ? $this->allowedCategoryCodes[0] : '';
        }
    }

    protected function validateCategoryForSigning(): void
    {
        Repository::carePlan()->assertCategoryForEncounter($this->form->category, $this->form->encounter, (int) $this->personId);
    }

    protected function refreshAuthorDisplay(): void
    {
        $party = Auth::user()?->getCarePlanWriterEmployee($this->form->termsOfService ?: null)?->party;
        $this->form->author = implode(' ', array_filter([
            $party?->last_name, $party?->first_name, $party?->second_name,
        ]));
    }

    private function loadDoctorsAndDictionaries(): void
    {
        // Load doctors for co-authors
        $legalEntity = legalEntity();
        if ($legalEntity) {
            $this->doctors = Repository::carePlan()->doctors($legalEntity->id);
        }

        // Load dictionaries via FormTrait pattern
        try {
            $this->getDictionary();
            $this->categories = $this->dictionaries['eHealth/care_plan_categories'] ?? [];
        } catch (Exception $exception) {
            report($exception);
            Log::warning('CarePlanCreate: failed to load dictionaries: ' . $exception->getMessage());
        }
    }

    /**
     * Search for a patient in the local registry (same criteria as the patients page).
     */
    public function searchForPatient(): void
    {
        if (!$this->allowsPatientChange || !Auth::user()?->can('create', CarePlan::class)) {
            return;
        }

        try {
            $validated = $this->patientSearch->validate();
        } catch (ValidationException $exception) {
            Session::flash('error', $exception->validator->errors()->first());
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        $firstName = $validated['firstName'];
        $lastName = $validated['lastName'];
        $birthDate = convertToYmd($validated['birthDate']);

        $this->patientSearchResults = Repository::carePlan()->searchPatients($firstName, $lastName, $birthDate);

        if ($this->patientSearchResults === []) {
            Session::flash('error', __('patients.nobody_found') . '. ' . __('patients.try_change_search_parameters'));
        }
    }

    /**
     * Select a patient from the search results.
     */
    public function selectPatient(int $personId): void
    {
        if (!$this->allowsPatientChange || !Auth::user()?->can('create', CarePlan::class)) {
            return;
        }

        $person = Repository::carePlan()->findPatient($personId) ?? abort(404);

        $this->personId = $person->id;
        $this->uuid = $person->uuid;
        $this->patientUuid = $person->uuid;
        $this->patientFullName = $person->fullName;
        $this->verificationStatus = $person->verificationStatus;
        $this->declarationNumber = $person->declarations->first()?->declarationNumber ?? null;

        $birthDate = $person->birth_date
            ? Carbon::parse($person->birth_date)->format(config('app.date_format'))
            : null;
        $this->form->patient = trim($person->fullName . ($birthDate ? ' · ' . $birthDate : ''));
        $this->form->medicalNumber = Repository::carePlan()->nextDisplayNumber();
        $this->form->encounter = '';
        $this->form->category = '';
        $this->form->episodes = [];
        $this->form->medicalRecords = [];
        $this->conditionUuid = '';
        $this->draftUuid = Str::uuid()->toString();
        $this->allowedCategoryCodes = [];
        $this->diagnoses = [];
        $this->patientSearchResults = [];
        $this->loadAvailableEncounters();

        $this->synchronizeSystemFields();
    }

    /**
     * Reset patient selection and return to search mode.
     */
    public function clearSelectedPatient(): void
    {
        if (!$this->allowsPatientChange || !Auth::user()?->can('create', CarePlan::class)) {
            return;
        }

        $this->personId = 0;
        $this->patientUuid = '';
        $this->uuid = '';
        $this->patientFullName = __('care-plan.new_care_plan');
        $this->verificationStatus = '';
        $this->declarationNumber = null;
        $this->form->patient = '';
        $this->form->encounter = '';
        $this->form->category = '';
        $this->form->episodes = [];
        $this->form->medicalRecords = [];
        $this->conditionUuid = '';
        $this->draftUuid = Str::uuid()->toString();
        $this->allowedCategoryCodes = [];
        $this->availableEncounters = [];
        $this->authMethods = [];
        $this->diagnoses = [];
        $this->patientSearchResults = [];
        $this->form->medicalNumber = Repository::carePlan()->nextDisplayNumber();
    }

    /**
     * Handle validation failure: dispatch flash + scroll events.
     */
    protected function handleValidationFailed(ValidationException $exception, bool $closeModal = false): void
    {
        $message = $exception->validator->errors()->first() ?: (__('validation.failed'));

        Session::flash('error', $message);
        $this->setErrorBag($exception->validator->getMessageBag());

        $firstKey = (string) array_key_first($exception->validator->errors()->messages());
        $selector = match ($firstKey) {
            'form.category', 'category' => '#category',
            'form.periodEnd', 'periodEnd' => '#period_end',
            'form.periodStart', 'periodStart' => '#period_start',
            default => null,
        };

        if ($selector !== null) {
            $this->dispatch('scroll-to-element', selector: $selector);
        } else {
            $this->dispatch('scroll-to-error');
        }

        if ($closeModal) {
            $this->showSignatureModal = false;
        }
    }

    /**
     * Look up a NEW care_plan approval on the patient-scoped eHealth endpoint.
     *
     * GET /api/approvals is rejected by Cloudflare WAF; list by patient instead.
     *
     * @return array<string, mixed>|null
     */
    private function findNewCarePlanApproval(string $carePlanUuid): ?array
    {
        $patientUuid = $this->patientUuid ?: $this->uuid;
        if ($patientUuid === '' || $carePlanUuid === '') {
            return null;
        }

        try {
            // GET /api/patients/{id}/approvals rejects `status` (422 additional properties).
            $response = EHealth::approval()->getPatientApprovals($patientUuid, [
                'granted_resource_type' => 'care_plan',
                'granted_resources' => $carePlanUuid,
            ]);
            $approvals = $response->validate();
            $approvalsData = is_array($approvals) ? ($approvals['data'] ?? $approvals) : [];
            if (!is_array($approvalsData) || $approvalsData === []) {
                return null;
            }

            foreach ($approvalsData as $approval) {
                if (!is_array($approval)) {
                    continue;
                }

                foreach ($approval['granted_resources'] ?? [] as $resource) {
                    if (($resource['identifier']['value'] ?? null) === $carePlanUuid) {
                        return $approval;
                    }
                }
            }

            $first = $approvalsData[0] ?? null;

            return is_array($first) ? $first : null;
        } catch (Exception $exception) {
            Log::warning('CarePlanCreate: Failed to fetch approvals: '.$exception->getMessage());

            return null;
        }
    }

    public function openActivationManually(): void
    {
        $carePlan = $this->carePlanUuid
            ? Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId)
            : null;

        if ($carePlan === null || !$this->guardApprovalAccess($carePlan)) {
            return;
        }

        if ($this->shouldSkipPatientAuthUi($carePlan)) {
            $this->createApproval('');

            return;
        }

        if (!$this->approvalId && $this->carePlanUuid) {
            $matchedApproval = $this->findNewCarePlanApproval($this->carePlanUuid);
            $this->approvalId = $matchedApproval['id'] ?? null;
            if (is_array($matchedApproval)) {
                $this->currentAuthMethod = $matchedApproval['authentication_method_current'] ?? $this->currentAuthMethod;
            }
        }

        if ($this->approvalId) {
            try {
                $result = app(CarePlanApprovalService::class)->pendingConfirmation($this->patientUuid, $this->approvalId);
                if ($result->isGranted()) {
                    Session::flash('success', __('care-plan.approval_verified'));
                    $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan->id], navigate: true);

                    return;
                }
                $this->currentAuthMethod = $result->authMethod;
                $this->openAuthModal();
            } catch (Throwable $exception) {
                Session::flash('error', __('care-plan.approval_result_unavailable'));
            }
        } else {
            $this->openMethodSelectionModal();
        }
    }

    /** Load the patient's verified authentication options when opening the approval method selector. */
    public function openMethodSelectionModal(): void
    {
        $carePlan = $this->carePlanUuid
            ? Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId)
            : null;

        if ($carePlan === null || !$this->guardApprovalAccess($carePlan)) {
            return;
        }

        if ($this->shouldSkipPatientAuthUi($carePlan)) {
            $this->createApproval('');

            return;
        }

        if (!empty($this->form->periodEnd)) {
            Session::flash('error', __('care-plan.period_end_warning'));
        }

        try {
            $this->authMethods = EHealth::person()->getAuthMethods($this->patientUuid)->validate();
            $this->showMethodSelectionModal = true;
        } catch (Exception $e) {
            Log::error('CarePlanCreate: failed to load auth methods: ' . $e->getMessage());
            Session::flash('error', __('care-plan.auth_methods_unavailable'));
        }
    }

    public function selectAuthMethod(string $methodUuid): void
    {
        $carePlan = $this->carePlanForSms();
        if (!$this->guardApprovalAccess($carePlan)) {
            return;
        }
        $this->currentAuthMethod = $this->selectedApprovalAuthMethod($methodUuid);
        $this->showMethodSelectionModal = false;
        $this->createApproval($methodUuid);
    }

    protected function createApproval(string $methodUuid): void
    {
        try {
            // Resolve the employee UUID that will be granted access.
            // Priority: explicitly selected doctor on the form → authenticated user's DOCTOR/SPECIALIST employee.
            // If neither resolves, skip approval creation and instruct the user to create it manually.
            $employeeUuid = (!empty($this->form->doctor) ? $this->form->doctor : null)
                ?? Auth::user()?->getCarePlanWriterEmployee($this->form->termsOfService ?: null)?->uuid;

            if (!$employeeUuid) {
                $carePlan = Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId);
                session()->flash('info', __('Не вдалося визначити лікаря для створення дозволу. Перейдіть на вкладку "Дозволи" та створіть дозвіл вручну.'));
                $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan?->id ?? $this->carePlanUuid], navigate: true);

                return;
            }

            $carePlan = Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId) ?? abort(404);
            $approvalService = app(CarePlanApprovalService::class);
            $skipsOtp = $this->shouldSkipPatientAuthUi($carePlan);

            $result = $approvalService->create(
                carePlan: $carePlan,
                patientUuid: $this->patientUuid,
                employeeUuid: $employeeUuid,
                accessLevel: 'write',
                authorizeWith: $skipsOtp ? null : ($methodUuid ?: null),
                user: Auth::user(),
                bearerToken: Session::get(config('ehealth.api.oauth.bearer_token')),
            );

            if ($result->isAsync()) {
                $this->approvalId = $result->approvalId;
                $this->pollingLinkId = $result->pollingLinkId;
                $this->isPolling = true;

                return;
            }

            $this->approvalId = $result->approvalId;

            if ($result->requiresConfirmation() && !$skipsOtp) {
                $this->currentAuthMethod = $result->authMethod ?? $this->currentAuthMethod;
                $this->openAuthModal();

                return;
            }

            Session::flash('success', $skipsOtp
                ? __('care-plan.approval_inpatient_granted')
                : __('План лікування успішно активовано.'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan->id], navigate: true);
        } catch (Exception $e) {
            Log::error('CarePlanCreate: failed to create approval: ' . $e->getMessage());
            $msg = __('Не вдалося створити запит на дозвіл: ') . $e->getMessage();
            Session::flash('error', $msg);
        }
    }

    /**
     * Called by wire:poll.2s while $isPolling === true.
     */
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
            $msg = $status->errorMessage ?: __('Не вдалося обробити запит на дозвіл. Спробуйте ще раз.');
            Session::flash('error', $msg);

            return;
        }

        if ($status->approvalId) {
            $this->approvalId = $status->approvalId;
        }

        $carePlan = Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId);
        $skipsOtp = $this->shouldSkipPatientAuthUi($carePlan);

        if ($status->requiresConfirmation() && !$skipsOtp) {
            $this->currentAuthMethod = $status->authMethod ?? $this->currentAuthMethod;
            $this->openAuthModal();

            return;
        }

        Session::flash('success', $skipsOtp
            ? __('care-plan.approval_inpatient_granted')
            : __('План лікування успішно активовано.'));
        $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan?->id ?? $this->carePlanUuid], navigate: true);
    }

    /**
     * Save the form as a local CarePlan draft without sending it to eHealth.
     *
     * Check the form's access context, resolve the patient and encounter, and validate
     * the fields and clinical selections. Map the accepted form to a Model DTO and
     * hand it to the repository for a transactional write. Open the draft editor on
     * success and report authorization, validation or storage errors in the form.
     *
     * @return void
     */
    public function save(): void
    {
        $user = Auth::user();
        $legalEntity = legalEntity();
        if ($user === null || $legalEntity === null || $this->draftUuid === null
            || !$this->draftContextMatches($user) || $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return;
        }

        $repository = Repository::carePlan();

        try {
            $person = $repository->findPatient((int) $this->personId);
            if ($person === null || !Str::isUuid((string) $person->uuid)) {
                throw ValidationException::withMessages(['form.patient' => __('validation.required', [
                    'attribute' => __('care-plan.patient'),
                ])]);
            }
            $encounter = $this->draftEncounter($person);
            if ($encounter === null) {
                throw ValidationException::withMessages(['form.encounter' => __('validation.exists', [
                    'attribute' => __('care-plan.encounter'),
                ])]);
            }

            $this->synchronizeSystemFields();
            $coAuthors = array_column($repository->doctors($legalEntity->id), 'uuid');
            $episodes = $this->draftEpisodes($person, $encounter, $user);
            $medicalRecords = $this->draftMedicalRecords($person, $encounter, $user);
            $validated = $this->form->validateDraft($coAuthors, $episodes->keys()->all(), $medicalRecords->keys()->all());

            $repository->assertCategoryForEncounter($this->form->category, $encounter->uuid, $person->id);
            $addresses = $repository->encounterData($encounter->uuid, $person->id, $this->conditionUuid ?: null)['addresses'];
            if ($addresses === []) {
                throw ValidationException::withMessages(['form.category' => __('care-plan.category_diagnosis_unavailable')]);
            }

            $author = $user->getCarePlanWriterEmployee($this->form->termsOfService);
            if ($author === null || !Str::isUuid((string) $author->uuid)) {
                throw ValidationException::withMessages(['form.author' => __('care-plan.referral_api_no_active_employee')]);
            }

            $mapper = objectMapper();
            $draft = $mapper->map($this->form, CarePlanData::class);
            $draft->uuid = $this->draftUuid;
            $draft->personId = $person->id;
            $draft->legalEntityId = $legalEntity->id;
            $draft->authorId = $author->id;
            $draft->encounterId = $encounter->id;
            $draft->prepareLocal(
                $mapper,
                $person->uuid,
                $author->uuid,
                $legalEntity->uuid,
                $addresses,
                $validated['coAuthors'],
                $validated['episodes'],
                array_intersect_key($medicalRecords->all(), array_flip($validated['medicalRecords'])),
            );
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return;
        }

        try {
            $carePlan = $repository->saveLocally(collect([$draft]))->first();
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());

            return;
        }

        Session::flash('success', __('care-plan.draft_saved'));
        $this->redirectRoute('care-plans.edit', [$legalEntity, $carePlan->id], navigate: true);
    }

    protected function isDraftCreation(): bool
    {
        return !($this instanceof CarePlanUpdate);
    }

    // TODO: Move this local form-context check into CarePlanForm when its draft validation is consolidated.
    protected function draftContextMatches(User $user): bool
    {
        return $this->draftOpenedById === $user->id
            && $this->draftLegalEntityId !== null
            && $this->draftLegalEntityId === legalEntity()?->id;
    }

    protected function draftEncounter(Person $person): ?Encounter
    {
        $user = Auth::user();
        if ($user === null || !Str::isUuid($this->form->encounter)
            || !$this->draftContextMatches($user) || $user->cannot('create', CarePlan::class)) {
            return null;
        }

        $encounter = Repository::carePlan()->findEncounter($this->form->encounter, $person->id, forDraftCreation: true);
        if ($encounter === null || $user->cannot('view', [$encounter, $person])) {
            return null;
        }

        return $encounter;
    }

    /** @return Collection<string, string> */
    protected function draftEpisodes(Person $person, Encounter $encounter, User $user): Collection
    {
        return $person->filterRecordsWithMerged(Episode::query())
            ->where(fn ($query) => $query->whereStatus(EpisodeStatus::ACTIVE)->orWhere('uuid', $encounter->episode?->value))
            ->get()
            ->toBase()
            ->filter(fn (Episode $episode): bool => $user->can('view', [$episode, $person]))
            ->mapWithKeys(fn (Episode $episode): array => [$episode->uuid => $episode->name]);
    }

    /** @return Collection<string, string> */
    protected function draftMedicalRecords(Person $person, Encounter $encounter, User $user): Collection
    {
        $procedures = $person->filterRecordsWithMerged(Procedure::forEncounter($encounter->uuid))
            ->whereStatus(ProcedureStatus::COMPLETED)
            ->with('code')->get()->toBase()
            ->filter(fn (Procedure $record): bool => $user->can('view', [$record, $person]));
        $reports = $person->filterRecordsWithMerged(DiagnosticReport::forEncounter($encounter->uuid))
            ->final()
            ->with('code')->get()->toBase()
            ->filter(fn (DiagnosticReport $record): bool => $user->can('view', [$record, $person]));

        return $procedures->concat($reports)->mapWithKeys(function (Procedure|DiagnosticReport $record): array {
            $type = $record instanceof Procedure ? 'procedure' : 'diagnostic_report';
            $label = $record->code?->displayValue ?: ($record->code?->value ?: $record->uuid);

            return [$type.':'.$record->uuid => __('care-plan.emz_type.'.$type).' — '.$label];
        });
    }

    public function cancel(): void
    {
        $encounterUuid = $this->form->encounter;
        if ($encounterUuid) {
            $encounter = Repository::carePlan()->findEncounter($encounterUuid, $this->personId);
            if ($encounter) {
                $this->redirectRoute('persons.encounters.package.show', [legalEntity(), $this->personId, $encounter->id], navigate: true);

                return;
            }
        }
        if ($this->personId > 0) {
            $this->redirectRoute('persons.care-plans', [legalEntity(), $this->personId], navigate: true);

            return;
        }

        $this->redirectRoute('care-plans.index', legalEntity(), navigate: true);
    }

    protected function loadAvailableEncounters(): void
    {
        $this->availableEncounters = $this->personId
            ? Repository::carePlan()->encounterChoices($this->personId, forDraftCreation: $this->isDraftCreation())
            : [];
    }

    public function updatedFormEncounter($value): void
    {
        if ($this->isDraftCreation()) {
            $person = Repository::carePlan()->findPatient((int) $this->personId);
            $encounter = $person ? $this->draftEncounter($person) : null;
            $this->form->encounter = $encounter?->uuid ?? '';
            $this->form->episodes = [];
            $this->form->medicalRecords = [];
            $this->conditionUuid = '';
        } else {
            $encounter = $value ? Repository::carePlan()->findEncounter($value, $this->personId) : null;
        }

        $this->synchronizeSystemFields();
        if ($encounter !== null) {
            $this->applyEncounterToForm($encounter);
        } elseif (!$this->form->encounter) {
            $this->form->medicalNumber = Repository::carePlan()->nextDisplayNumber();
            $this->diagnoses = [];
            $this->form->episodes = [];
        }

        $this->refreshCategoryOptions();
    }

    protected function applyEncounterToForm(Encounter $encounter): void
    {
        $this->form->medicalNumber = (string) $encounter->id;
        if ($this->form->title === '') {
            $date = $encounter->period?->start ? Carbon::parse($encounter->period->start)->format('d.m.Y') : now()->format('d.m.Y');
            $this->form->title = __('План лікування від ').$date;
        }

        $episodeUuid = $encounter->episode?->value;
        if ($episodeUuid !== null) {
            if ($this->isDraftCreation()) {
                $person = Repository::carePlan()->findPatient((int) $this->personId);
                $user = Auth::user();
                if ($person && $user && $this->draftEpisodes($person, $encounter, $user)->has($episodeUuid)) {
                    $this->form->episodes = [$episodeUuid];
                }
            } elseif ($episode = Repository::carePlan()->findEpisode($episodeUuid)) {
                $this->form->episodes = [[
                    'uuid' => $episode->uuid,
                    'name' => $episode->name,
                    'date' => $episode->period?->start ? Carbon::parse($episode->period->start)->format('d.m.Y') : '',
                ]];
            }
        } else {
            $this->form->episodes = [];
        }

        $this->diagnoses = $this->buildDiagnosesForUi($encounter);
    }

    /**
     * Build the UI list of diagnoses for an encounter. EncounterDiagnose::condition() resolves to
     * the Identifier row (its uuid lives in ->value), not the actual Condition, so we look the
     * Condition up locally. The existing draft editor can load missing conditions from eHealth.
     *
     * @return array<int, array{date: string, name: string}>
     */
    protected function buildDiagnosesForUi(Encounter $encounter): array
    {
        return $encounter->diagnoses->map(function ($d) {
            $conditionUuid = $d->condition?->value;
            $actualCondition = null;
            if ($conditionUuid) {
                $actualCondition = Repository::carePlan()->findCondition($conditionUuid, $this->personId);
                if (!$actualCondition && !$this->isDraftCreation()) {
                    Log::info('CarePlanCreate: condition not found in local SQL DB, attempting to fetch from eHealth', [
                        'condition_uuid' => $conditionUuid
                    ]);
                    try {
                        $conditionData = EHealth::condition()->getById($this->uuid, $conditionUuid)->validate();
                        MedicalEventsRepository::condition()->store([Arr::toCamelCase($conditionData)], Repository::carePlan()->findPatient($this->personId));
                        $actualCondition = Repository::carePlan()->findCondition($conditionUuid, $this->personId);
                    } catch (Exception $e) {
                        Log::error('CarePlanCreate: failed to fetch condition from eHealth', [
                            'condition_uuid' => $conditionUuid,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            return [
                'date' => $actualCondition?->asserted_date
                    ? Carbon::parse($actualCondition->asserted_date)->format('d.m.Y')
                    : '-',
                'name' => ($actualCondition?->code?->text ?: null)
                    ?? ($actualCondition?->code?->coding?->first()?->code ?: null)
                    ?? '-',
            ];
        })->toArray();
    }

    /**
     * Verify the SMS code or confirm offline document verification.
     */
    public function verify(): void
    {
        if ($this->isSmsResending) {
            Session::flash('info', __('care-plan.sms_resend_processing'));

            return;
        }
        $plan = $this->carePlanForSms();
        if (!$this->guardApprovalAccess($plan)) {
            return;
        }
        app(MedicalRequestOwnership::class)->approvalForCarePlan($plan, $this->approvalId ?? '');
        $this->validate($this->approvalVerificationRules(), $this->approvalVerificationMessages());

        try {
            $response = app(CarePlanApprovalService::class)->verify(
                $this->patientUuid,
                $this->approvalId,
                $this->isOfflineAuthMethod() ? null : (int) $this->verificationCode,
            );

            if ($response->successful()) {
                $this->closeAuthModal();
                Session::flash('success', __('План лікування успішно активовано.'));
                $carePlan = Repository::carePlan()->findByUuid($this->carePlanUuid, (int) legalEntity()->id, $this->personId);
                $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan?->id ?? $this->carePlanUuid], navigate: true);
            }
        } catch (Exception $e) {
            Log::warning('Care plan approval confirmation failed', ['exceptionType' => $e::class, 'statusCode' => $e->getCode()]);
            $message = $e instanceof EHealthValidationException ? $e->getFormattedMessage()
                : ($e instanceof EHealthResponseException && ApprovalApi::errorMessage($e->response) === 'Invalid verification code'
                    ? __('errors.ehealth.messages.invalid_verification_code') : __('care-plan.approval_verify_error'));
            $this->addError('verificationCode', $message);
            Session::flash('error', $message);
        }
    }

    protected function carePlanForSms(): CarePlan
    {
        $plan = Repository::carePlan()->findByUuid((string) $this->carePlanUuid, (int) legalEntity()->id, $this->personId);
        abort_unless($plan instanceof CarePlan, 404);

        return $plan;
    }

    protected function onSmsApprovalAlreadyGranted(): void
    {
        $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlanForSms()->id], navigate: true);
    }

    /**
     * Initialize the component.
     */
    protected function initializeComponent(): void
    {
        // Handled by BasePatientComponent for id, uuid, patientFullName
        $this->patientUuid = $this->uuid;
    }

    protected function resolveEncounterData(): array
    {
        if (!$this->form->encounter) {
            return ['id' => null, 'addresses' => [], 'period_start' => null];
        }
        $encounter = Repository::carePlan()->findEncounter($this->form->encounter, $this->personId);
        if ($encounter) {
            $this->buildDiagnosesForUi($encounter);
        }

        return Repository::carePlan()->encounterData($this->form->encounter, $this->personId, $this->conditionUuid ?: null);
    }

    /**
     * INPATIENT plans at this facility skip the SMS / method-selection step.
     * OUTPATIENT (typical PMD) still requires patient OTP.
     */
    protected function shouldSkipPatientAuthUi(?CarePlan $carePlan): bool
    {
        if ($carePlan && app(CarePlanApprovalService::class)->skipsPatientOtp($carePlan)) {
            return true;
        }

        if (strtoupper((string) $this->form->termsOfService) !== CarePlanTermsOfService::INPATIENT->value) {
            return false;
        }

        $legalEntity = legalEntity();
        if ($legalEntity === null) {
            return false;
        }

        if ($carePlan === null || $carePlan->legalEntityId === null) {
            return true;
        }

        return (int) $carePlan->legalEntityId === (int) $legalEntity->id;
    }

    protected function authorHasActiveRoleForTermsOfService(Employee $author, string $termsOfService): bool
    {
        return Repository::carePlan()->authorHasActiveRoleForTermsOfService($author, $termsOfService);
    }

    protected function logCarePlanAuthorRoleDebug(?Employee $author, string $termsOfService): void
    {
        Log::debug('Care Plan author role validation', ['employeeUuid' => $author?->uuid, 'termsOfService' => $termsOfService, 'hasMatchingRole' => $author !== null && $this->authorHasActiveRoleForTermsOfService($author, $termsOfService)]);
    }

    public function render()
    {
        $user = Auth::user();
        $person = $this->personId ? Repository::carePlan()->findPatient($this->personId) : null;
        $encounter = $person ? $this->draftEncounter($person) : null;

        return view('livewire.care-plan.care-plan-create', [
            'draftOnly' => true,
            'draftEpisodeChoices' => $encounter && $user ? $this->draftEpisodes($person, $encounter, $user) : collect(),
            'draftRecordChoices' => $encounter && $user ? $this->draftMedicalRecords($person, $encounter, $user) : collect(),
        ]);
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
}
