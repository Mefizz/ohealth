<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Dto\CarePlan\Model as CarePlanData;
use App\Enums\Episode\Status as EpisodeStatus;
use App\Enums\Person\ProcedureStatus;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\DiagnosticReport;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\Episode;
use App\Models\MedicalEvents\Sql\Procedure;
use App\Models\Person\Person;
use App\Models\User;
use App\Repositories\Repository;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CarePlanCreate extends CarePlanDraftComponent
{
    #[Locked]
    public ?string $draftUuid = null;

    #[Locked]
    public ?int $draftLegalEntityId = null;

    #[Locked]
    public ?int $draftOpenedById = null;

    public function mount(LegalEntity $legalEntity, $personId = null, $encounter = null, ?Person $person = null): void
    {
        $this->draftUuid = Str::uuid()->toString();
        $this->draftLegalEntityId = $legalEntity->id;
        $this->draftOpenedById = Auth::id();

        $this->loadPatientForm($legalEntity, $personId, $person);
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
        $carePlan = $this->saveNewDraft();
        if ($carePlan !== null) {
            Session::flash('success', __('care-plan.draft_saved'));
            $this->redirectRoute('care-plans.edit', [legalEntity(), $carePlan->id], navigate: true);
        }
    }

    public function startSigningProcess(): void
    {
        $carePlan = $this->saveNewDraft();
        if ($carePlan !== null) {
            $this->redirectRoute('care-plans.show', [legalEntity(), $carePlan->id, 'action' => 'sign_plan'], navigate: true);
        }
    }

    protected function saveNewDraft(): ?CarePlan
    {
        $user = Auth::user();
        $legalEntity = legalEntity();
        if ($user === null || $legalEntity === null || $this->draftUuid === null
            || !$this->draftContextMatches($user) || $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return null;
        }

        $repository = Repository::carePlan();

        try {
            $person = $repository->findPatient((int) $this->personId);
            if ($person === null || !Str::isUuid((string) $person->uuid)) {
                throw ValidationException::withMessages(['form.patient' => __('validation.required', [
                    'attribute' => __('care-plan.patient'),
                ])]);
            }
            $encounter = $this->validateDraftEncounter($person);

            $this->synchronizeSystemFields();
            $coAuthors = array_column($repository->doctors($legalEntity->id), 'uuid');
            $episodes = $this->draftEpisodes($person, $encounter, $user);
            $medicalRecords = $this->form->medicalRecords === [] ? collect() : $this->draftMedicalRecords($person, $encounter, $user);
            $validated = $this->form->validateDraft($coAuthors, $episodes->keys()->all(), $medicalRecords->keys()->all());

            $addresses = $this->draftAddresses($person, $encounter);
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

            return null;
        }

        try {
            $carePlan = $repository->saveLocally(collect([$draft]), $mapper)->first();
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());

            return null;
        }

        return $carePlan;
    }

    protected function draftContextMatches(User $user): bool
    {
        return $this->draftOpenedById === $user->id
            && $this->draftLegalEntityId !== null
            && $this->draftLegalEntityId === legalEntity()?->id;
    }

    protected function draftEpisodes(Person $person, Encounter $encounter, User $user): Collection
    {
        return $person->filterRecordsWithMerged(Episode::query())
            ->where(fn ($query) => $query->whereStatus(EpisodeStatus::ACTIVE)->orWhere('uuid', $encounter->episode?->value))
            ->get()
            ->toBase()
            ->filter(fn (Episode $episode): bool => $user->can('view', [$episode, $person]))
            ->mapWithKeys(fn (Episode $episode): array => [$episode->uuid => $episode->name]);
    }

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
        $this->displayMedicalNumber(Repository::carePlan()->nextDisplayNumber());
        $this->form->encounter = '';
        $this->form->category = '';
        $this->form->episodes = [];
        $this->form->medicalRecords = [];
        $this->conditionUuid = '';
        $this->draftUuid = Str::uuid()->toString();
        $this->allowedCategoryCodes = [];
        $this->diagnoses = [];
        $this->patientSearchResults = [];
        $this->availableEpisodes = Repository::carePlan()->activeEpisodes($person);
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
        $this->availableEpisodes = [];
        $this->authMethods = [];
        $this->diagnoses = [];
        $this->patientSearchResults = [];
        $this->displayMedicalNumber(Repository::carePlan()->nextDisplayNumber());
    }

    public function render(): View
    {
        return view('livewire.care-plan.care-plan-create');
    }
}
