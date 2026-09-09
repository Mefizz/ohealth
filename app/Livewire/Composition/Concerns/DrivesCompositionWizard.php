<?php

declare(strict_types=1);

namespace App\Livewire\Composition\Concerns;

use App\Classes\eHealth\EHealth;
use App\Enums\Person\AuthenticationMethod;
use App\Enums\Person\CompositionStatus;
use App\Enums\Person\CompositionType;
use App\Enums\Person\EncounterStatus;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Models\MedicalEvents\Sql\Composition;
use App\Models\Person\Person;
use App\Models\Preperson;
use App\Services\MedicalEvents\CompositionLifecycleService;
use App\Services\SignatureService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Shared wizard mechanics for both conclusion types: encounter picker, authentication
 * method, async job, print form and the two KEP steps (create then sign).
 *
 * Each concrete wizard still owns its details form and payload, because those are what
 * the contract treats as two different documents.
 */
trait DrivesCompositionWizard
{
    public const int STEP_ENCOUNTER = 1;

    public const int STEP_AUTH_METHOD = 2;

    public const int STEP_DETAILS = 3;

    public const int STEP_AWAITING_JOB = 4;

    public const int STEP_REVIEW = 5;

    /**
     * Nested in the encounter drawer — no patient layout, encounter is passed as a prop.
     */
    public bool $embedded = false;

    public int $step = self::STEP_ENCOUNTER;

    /** Episode of the chosen encounter; needed to read the conclusion back. */
    #[Locked]
    public ?string $episodeUuid = null;

    public array $authMethods = [];

    /** Shown once the user chooses to proceed without an authentication method. */
    public bool $acknowledgedMissingAuthMethod = false;

    /** Phone used when creating an OTP authentication method from the wizard. */
    public string $newOtpPhone = '';

    /** Async auth-method request id while OTP SMS confirmation is pending. */
    public ?string $pendingAuthMethodRequestId = null;

    public string $authMethodVerificationCode = '';

    /** Active conclusions found via searchCompositions before create (TV 3.8.1.3 / 3.8.2.3). */
    public array $existingActiveRemote = [];

    #[Locked]
    public ?string $asyncJobId = null;

    public string $asyncJobStatus = '';

    public array $asyncJobErrors = [];

    #[Locked]
    public ?string $compositionUuid = null;

    public ?array $compositionDetail = null;

    public ?array $integrationData = null;

    public ?string $printFormHtml = null;

    public bool $showPrintModal = false;

    public bool $showSignatureModal = false;

    /**
     * Patient UUID whose encounters may carry this conclusion.
     *
     * A birth conclusion is filed against the newborn, which is not always the card
     * the wizard was opened from (the mother may be), so this is not simply `$this->uuid`.
     */
    abstract protected function encounterSubjectUuid(): string;

    /**
     * Person whose authentication methods inform the conclusion, or null when none apply.
     *
     * An unidentified patient cannot hold any; a birth conclusion uses the mother's.
     */
    abstract protected function authenticationSubjectUuid(): ?string;

    abstract protected function conclusionType(): CompositionType;

    /**
     * Policy ability checked before the create request is sent.
     */
    abstract protected function createAbility(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function mapperPayload(string $authorEmployeeUuid): array;

    /**
     * Patient the local projection is stored against.
     *
     * A birth conclusion always belongs to the newborn preperson, even when the wizard
     * started from the mother's card.
     */
    abstract protected function storagePatient(): Person|Preperson;

    /**
     * Rules that make the details step submittable, keyed for the Livewire form.
     *
     * @return array<string, mixed>
     */
    abstract protected function detailsRules(): array;

    /**
     * Encounters the user may build a conclusion on.
     *
     * TV 3.8.1.5.1 / 3.8.2.5.1 restrict the choice to encounters the user performed
     * themselves, and eHealth additionally rejects anything that is not finished.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function availableEncounters(): Collection
    {
        $authorUuid = $this->authorEmployeeUuid();
        $subjectUuid = $this->encounterSubjectUuid();

        if ($authorUuid === null || $subjectUuid === '') {
            return collect();
        }

        try {
            $params = array_filter(['managing_organization_id' => legalEntity()?->uuid]);
            $encounters = EHealth::encounter()
                ->getBySearchParams($subjectUuid, $params)
                ->validate();
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Error loading encounters for a medical conclusion');

            return collect();
        }

        return collect($encounters)
            ->filter(static fn (array $encounter) => filled(data_get($encounter, 'uuid')))
            ->filter(static fn (array $encounter) => data_get($encounter, 'status') === EncounterStatus::FINISHED->value)
            ->filter(static fn (array $encounter) => data_get($encounter, 'performer.identifier.value') === $authorUuid)
            ->sortByDesc(static fn (array $encounter) => data_get($encounter, 'period.start'))
            ->values();
    }

    /**
     * Whether OFFLINE authentication may be created here (МВН only — TV 3.8.1.4.1).
     */
    #[Computed]
    public function canCreateOfflineAuthMethod(): bool
    {
        return $this->conclusionType() === CompositionType::NEWBORN
            && filled($this->authenticationSubjectUuid());
    }

    /**
     * Link to the patient card where THIRD_PERSON / full auth management lives.
     */
    #[Computed]
    public function patientAuthManagementUrl(): ?string
    {
        $subjectUuid = $this->authenticationSubjectUuid();

        if ($subjectUuid === null || $subjectUuid === '') {
            return null;
        }

        $person = Person::query()->where('uuid', $subjectUuid)->first();

        if ($person === null || legalEntity() === null) {
            return null;
        }

        return route('persons.patient-data', [legalEntity(), 'person' => $person->id]);
    }

    /**
     * When the wizard opened from an encounter drawer, the encounter step is skipped and
     * "Back" from auth closes the drawer instead of showing an empty picker.
     */
    #[Computed]
    public function encounterIsLocked(): bool
    {
        return $this->embedded && filled($this->form->encounterUuid);
    }

    public function selectEncounter(string $encounterUuid): void
    {
        $encounter = $this->availableEncounters
            ->firstWhere('uuid', $encounterUuid);

        if (!$encounter) {
            Session::flash('error', __('compositions.errors.encounter_not_selectable'));

            return;
        }

        $this->form->encounterUuid = $encounterUuid;
        $this->episodeUuid = data_get($encounter, 'episode.identifier.value');

        $this->refreshExistingActiveRemote();
        $this->loadAuthMethods();
        $this->step = self::STEP_AUTH_METHOD;
    }

    /**
     * Auth-step back control: unlock the encounter picker, or close the drawer when locked.
     */
    public function goBackFromAuthMethod(): void
    {
        if ($this->encounterIsLocked) {
            $this->requestClose();

            return;
        }

        $this->step = self::STEP_ENCOUNTER;
    }

    /**
     * Ask the parent encounter page to close the composition drawer (no-op on full page).
     */
    public function requestClose(): void
    {
        if (!$this->embedded) {
            return;
        }

        $this->dispatch('composition-wizard-closed');
    }

    public function loadAuthMethods(): void
    {
        $this->authMethods = [];
        $this->pendingAuthMethodRequestId = null;
        $this->authMethodVerificationCode = '';
        $subjectUuid = $this->authenticationSubjectUuid();

        if ($subjectUuid === null || $subjectUuid === '') {
            return;
        }

        try {
            $this->authMethods = EHealth::person()
                ->getAuthMethods($subjectUuid)
                ->getData();
        } catch (EHealthConnectionException | EHealthException $exception) {
            Log::error('Failed to load authentication methods for a medical conclusion', [
                'focus' => $subjectUuid,
                'error' => $exception->getMessage(),
            ]);

            Session::flash('error', __('compositions.errors.auth_methods_failed'));
        }
    }

    public function selectAuthMethod(string $methodUuid): void
    {
        $this->form->informWithUuid = $methodUuid;
        $this->acknowledgedMissingAuthMethod = false;
        $this->step = self::STEP_DETAILS;
    }

    /**
     * Continue without informing the patient by SMS (TV 3.8.1.4.4, 3.8.2.4.4).
     */
    public function skipAuthMethod(): void
    {
        $this->form->informWithUuid = null;
        $this->acknowledgedMissingAuthMethod = true;
        $this->step = self::STEP_DETAILS;
    }

    /**
     * Create an OFFLINE authentication method for the mother (TV 3.8.1.4.1).
     */
    public function createOfflineAuthMethod(): void
    {
        if (!$this->canCreateOfflineAuthMethod) {
            return;
        }

        $subjectUuid = $this->authenticationSubjectUuid();

        try {
            $response = EHealth::person()->insertAuthMethod($subjectUuid, AuthenticationMethod::OFFLINE);
            $requestId = data_get($response->getData(), 'id')
                ?? data_get($response->json(), 'data.id');

            if (filled($requestId)) {
                EHealth::person()->approveAuthMethod($subjectUuid, (string) $requestId);
            }

            $this->loadAuthMethods();
            Session::flash('success', __('compositions.messages.offline_auth_method_added'));
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Failed to create OFFLINE authentication method for a conclusion');
        }
    }

    /**
     * Start OTP authentication method creation (TV 3.8.1.4.1 / 3.8.2.4.1).
     */
    public function createOtpAuthMethod(): void
    {
        $subjectUuid = $this->authenticationSubjectUuid();

        if ($subjectUuid === null || $subjectUuid === '') {
            return;
        }

        try {
            $this->validate([
                'newOtpPhone' => ['required', 'string', 'regex:/^\+380\d{9}$/'],
            ]);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        try {
            $response = EHealth::person()->insertAuthMethod(
                $subjectUuid,
                AuthenticationMethod::OTP,
                $this->newOtpPhone
            );

            $this->pendingAuthMethodRequestId = data_get($response->getData(), 'id')
                ?? data_get($response->json(), 'data.id');

            Session::flash('success', __('compositions.messages.otp_auth_method_requested'));
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Failed to create OTP authentication method for a conclusion');
        }
    }

    /**
     * Confirm the pending OTP authentication method with the SMS code.
     */
    public function confirmOtpAuthMethod(): void
    {
        $subjectUuid = $this->authenticationSubjectUuid();

        if ($subjectUuid === null || !$this->pendingAuthMethodRequestId) {
            return;
        }

        try {
            $this->validate([
                'authMethodVerificationCode' => ['required', 'digits:4'],
            ]);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        try {
            EHealth::person()->approveAuthMethod(
                $subjectUuid,
                $this->pendingAuthMethodRequestId,
                ['verification_code' => $this->authMethodVerificationCode]
            );

            $this->pendingAuthMethodRequestId = null;
            $this->authMethodVerificationCode = '';
            $this->newOtpPhone = '';
            $this->loadAuthMethods();
            Session::flash('success', __('compositions.messages.otp_auth_method_added'));
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Failed to approve OTP authentication method for a conclusion');
        }
    }

    /**
     * Validate details and open the KEP modal for createComposition.
     *
     * The eHealth create endpoint only accepts a detached signature over the conclusion
     * payload, so this step must never post the raw mapper JSON.
     */
    public function openCreateSignatureModal(): void
    {
        $this->authorize($this->createAbility(), Composition::class);

        try {
            $this->form->validate($this->detailsRules());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        if ($this->authorEmployeeUuid() === null) {
            Session::flash('error', __('compositions.errors.author_not_found'));

            return;
        }

        $this->form->resetSigningFields();
        $this->showSignatureModal = true;
    }

    /**
     * @deprecated Use {@see openCreateSignatureModal()} — kept so older Blade bindings keep working.
     */
    public function reviewDetails(): void
    {
        $this->openCreateSignatureModal();
    }

    /**
     * Sign the createComposition payload and submit it to eHealth (TV 3.8.1.1.1 / 3.8.2.1.1).
     */
    public function submitComposition(): void
    {
        $this->authorize($this->createAbility(), Composition::class);

        try {
            $this->form->validate(array_merge($this->detailsRules(), $this->form->signingRules()));
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        $authorUuid = $this->authorEmployeeUuid();

        if ($authorUuid === null) {
            Session::flash('error', __('compositions.errors.author_not_found'));

            return;
        }

        try {
            $payload = $this->mapperPayload($authorUuid);
            Log::info('Signing medical conclusion create payload', [
                'type' => $this->conclusionType()->value,
            ]);

            $signedContent = app(SignatureService::class)->signData(
                $payload,
                $this->form->password,
                $this->form->knedp,
                $this->form->keyContainerUpload,
                Auth::user()->party->taxId
            );

            $job = $this->lifecycle()->create(['data' => $signedContent]);

            $this->asyncJobId = $job['id'];
            $this->asyncJobStatus = (string) ($job['status'] ?? CompositionLifecycleService::JOB_PENDING);
            $this->asyncJobErrors = [];
            $this->showSignatureModal = false;
            $this->form->resetSigningFields();
            $this->step = self::STEP_AWAITING_JOB;
        } catch (EHealthResponseException $exception) {
            $details = $exception->getDetails();
            $errText = data_get($details, 'error.message')
                ?? data_get($details, 'details.errorMessage')
                ?? data_get($details, 'description')
                ?? $exception->getMessage();

            $exception->handle('Failed to submit a medical conclusion', $errText);
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Failed to submit a medical conclusion');
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            Log::error('Failed to submit a medical conclusion', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Poll the async job until eHealth finishes the current request.
     *
     * The same poller covers create (TV 3.8.1.5.3 / 3.8.2.7) and the later sign, which
     * also returns a job. After a sign, the conclusion already exists locally so the
     * details are refreshed rather than resolved from scratch.
     */
    public function pollAsyncJob(): void
    {
        if (!$this->asyncJobId || $this->asyncJobStatus === CompositionLifecycleService::JOB_DONE) {
            return;
        }

        try {
            $status = $this->lifecycle()->jobStatus($this->asyncJobId);
        } catch (Throwable $exception) {
            Log::error('Failed to read the conclusion async job', ['error' => $exception->getMessage()]);

            return;
        }

        $this->asyncJobStatus = $status['status'];

        if ($status['status'] === CompositionLifecycleService::JOB_FAILED) {
            $this->asyncJobErrors = $status['errors'];

            return;
        }

        if ($status['status'] !== CompositionLifecycleService::JOB_DONE) {
            return;
        }

        if ($this->compositionUuid === null) {
            $this->compositionUuid = $status['compositionUuid']
                ?? $this->lifecycle()->resolveCreatedComposition(
                    [],
                    $this->encounterSubjectUuid(),
                    $this->form->encounterUuid,
                    $this->conclusionType()
                );
        }

        if ($this->compositionUuid === null) {
            $this->asyncJobErrors = [__('compositions.errors.created_not_found')];

            return;
        }

        $this->loadCompositionDetail();
        $this->step = self::STEP_REVIEW;
    }

    public function loadCompositionDetail(): void
    {
        if (!$this->compositionUuid || !$this->episodeUuid) {
            return;
        }

        try {
            $this->compositionDetail = $this->lifecycle()->fetchDetails(
                $this->encounterSubjectUuid(),
                $this->compositionUuid,
                $this->episodeUuid,
                $this->form->encounterUuid
            );

            $composition = $this->lifecycle()->storeLocal(
                $this->compositionDetail,
                $this->storagePatient(),
                $this->episodeUuid,
                $this->asyncJobId
            );

            if ($composition !== null) {
                try {
                    $this->integrationData = $this->lifecycle()->syncIntegration($composition);
                } catch (EHealthConnectionException | EHealthException) {
                    $this->integrationData = data_get($composition->data, '_integration');
                }
            }
        } catch (EHealthConnectionException | EHealthException $exception) {
            $exception->handle('Error reading the created medical conclusion');
        }
    }

    public function loadPrintForm(): void
    {
        if (!$this->compositionUuid || !$this->episodeUuid) {
            return;
        }

        try {
            $templateId = $this->conclusionType() === CompositionType::NEWBORN ? '1000' : '1001';
            $response = EHealth::composition()->getPrintForm(
                $this->encounterSubjectUuid(),
                $this->compositionUuid,
                $this->episodeUuid,
                $this->form->encounterUuid,
                $templateId
            );

            $this->printFormHtml = $response->body();
            $this->showPrintModal = true;
        } catch (EHealthConnectionException | EHealthException $exception) {
            Session::flash('error', __('compositions.errors.print_form_failed'));

            Log::error('Failed to load the conclusion print form', [
                'composition' => $this->compositionUuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Same eHealth print form, exposed as the mother-facing action (TV 3.8.1.8.3).
     */
    public function loadPrintFormForMother(): void
    {
        $this->loadPrintForm();
    }

    public function closePrintModal(): void
    {
        $this->showPrintModal = false;
        $this->printFormHtml = null;
    }

    public function openSigningModal(): void
    {
        $this->form->resetSigningFields();
        $this->showSignatureModal = true;
    }

    public function sign(): void
    {
        $composition = Composition::whereUuid($this->compositionUuid)->first();

        if (!$composition) {
            Session::flash('error', __('compositions.errors.not_found'));

            return;
        }

        $this->authorize('sign', $composition);

        try {
            $this->form->validate($this->form->signingRules());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        try {
            $signedContent = app(SignatureService::class)->signData(
                $this->compositionDetail ?? [],
                $this->form->password,
                $this->form->knedp,
                $this->form->keyContainerUpload,
                Auth::user()->party->taxId
            );

            $job = $this->lifecycle()->sign($composition->uuid, $signedContent);

            $composition->update(['async_job_id' => $job['id']]);

            $this->showSignatureModal = false;
            $this->form->resetSigningFields();
            $this->asyncJobId = $job['id'];
            $this->asyncJobStatus = (string) ($job['status'] ?? CompositionLifecycleService::JOB_PENDING);
            $this->asyncJobErrors = [];
            $this->step = self::STEP_AWAITING_JOB;

            Session::flash('success', __('compositions.messages.signature_submitted'));
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            Log::error('Failed to sign a medical conclusion', [
                'composition' => $this->compositionUuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Search eHealth for previously created non-error conclusions of this type (TV 3.8.1.3 / 3.8.2.3).
     */
    public function refreshExistingActiveRemote(): void
    {
        $this->existingActiveRemote = [];
        $subjectUuid = $this->encounterSubjectUuid();

        if ($subjectUuid === '') {
            return;
        }

        try {
            $rows = EHealth::composition()->search([
                'subject' => $subjectUuid,
                'type' => $this->conclusionType()->value,
            ])->validate();

            $this->existingActiveRemote = collect($rows)
                ->filter(static function (array $row): bool {
                    $status = CompositionStatus::fromEHealth(data_get($row, 'status'));

                    return $status !== null && $status !== CompositionStatus::ENTERED_IN_ERROR;
                })
                ->map(static fn (array $row): array => [
                    'uuid' => data_get($row, 'identifier.value'),
                    'title' => data_get($row, 'title'),
                    'status' => data_get($row, 'status'),
                    'date' => data_get($row, 'date'),
                ])
                ->values()
                ->all();
        } catch (EHealthConnectionException | EHealthException $exception) {
            Log::warning('Failed to search existing compositions before create', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Shared wizard state that both conclusions reset. Each child then restores the
     * fields that are unique to its form.
     *
     * @param  list<string>  $extra
     */
    protected function resetWizard(array $extra = []): void
    {
        $this->form->resetSigningFields();
        $this->reset(array_merge([
            'step',
            'episodeUuid',
            'authMethods',
            'acknowledgedMissingAuthMethod',
            'newOtpPhone',
            'pendingAuthMethodRequestId',
            'authMethodVerificationCode',
            'existingActiveRemote',
            'asyncJobId',
            'asyncJobStatus',
            'asyncJobErrors',
            'compositionUuid',
            'compositionDetail',
            'integrationData',
            'printFormHtml',
            'showPrintModal',
            'showSignatureModal',
        ], $extra));
    }

    protected function authorEmployeeUuid(): ?string
    {
        return Auth::user()?->getCompositionAuthorEmployee()?->uuid;
    }

    protected function lifecycle(): CompositionLifecycleService
    {
        return app(CompositionLifecycleService::class);
    }
}
