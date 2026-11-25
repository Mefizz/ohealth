<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Dto\CarePlan\Form as CarePlanFormData;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Models\Person\Person;
use App\Models\Preperson;
use App\Repositories\Repository;
use App\Services\MedicalEvents\Fhir;
use ArrayObject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class CarePlanEdit extends CarePlanDraftComponent
{
    #[Locked]
    public int $carePlanId;

    #[Locked]
    public string $draftRevision;

    #[Locked]
    public int $draftLegalEntityId;

    public function mount(
        LegalEntity $legalEntity,
        ?Person $person = null,
        ?Preperson $preperson = null,
        ?CarePlan $carePlan = null
    ): void {
        abort_if($carePlan === null, 404);

        $this->carePlanId = $carePlan->id;
        $this->draftLegalEntityId = $legalEntity->id;
        $draft = $this->carePlan;
        $repository = Repository::carePlan();
        $pending = $repository->hasPendingSubmission($draft);
        if ($pending || $draft->requiresFhirImport()) {
            Session::flash($pending ? 'info' : 'warning', __($pending ? 'care-plan.cannot_edit_non_draft' : 'care-plan.draft_backfill_required'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $draft->id], navigate: true);
            $this->skipRender();

            return;
        }

        $this->loadPatientForm($legalEntity, $draft->personId, $draft->person);
        $resource = $repository->getResource($draft);
        $this->draftRevision = $repository->draftRevision($resource);
        $fields = Fhir::carePlan()->fromFhir($resource, ['timezone' => config('app.timezone'), 'dateFormat' => config('app.date_format')]);
        $mapper = objectMapper();
        $formData = $mapper->map(new ArrayObject($fields), CarePlanFormData::class);
        $mapper->map($formData, $this->form);
        $this->form->episodes = $repository->resolveEpisodeLabels($this->form->episodes, $this->personId);
        $this->carePlanUuid = $resource['id'];
        $this->patientUuid = $draft->person->uuid;
        $this->form->patient = $draft->person->fullName ?? '';
        $this->displayMedicalNumber((string) ($draft->encounterId ?? ''));
        $this->synchronizeSystemFields();
        if ($draft->encounter) {
            $this->diagnoses = $this->buildDiagnosesForUi($draft->encounter);
        }
        $this->refreshCategoryOptions();
    }

    public function save(): void
    {
        if ($this->saveDraftChanges()) {
            Session::flash('success', __('care-plan.draft_updated'));
        }
    }

    public function startSigningProcess(): void
    {
        if (Auth::user()?->can('create', CarePlan::class) !== true) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return;
        }

        if ($this->saveDraftChanges()) {
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlanId, 'action' => 'sign_plan'], navigate: true);
        }
    }

    protected function saveDraftChanges(): bool
    {
        if (legalEntity()?->id !== $this->draftLegalEntityId) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return false;
        }

        try {
            $revision = Repository::carePlan()->withLockedDraft(
                $this->carePlanId,
                (int) $this->personId,
                $this->draftLegalEntityId,
                $this->saveLockedDraft(...)
            );
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return false;
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());

            return false;
        }

        if ($revision === null) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return false;
        }

        $this->draftRevision = $revision;
        unset($this->carePlan);

        return true;
    }

    protected function saveLockedDraft(CarePlan $draft): ?string
    {
        $user = Auth::user();
        if ($user?->can('update', $draft) !== true) {
            return null;
        }

        $repository = Repository::carePlan();
        $repository->assertDraftSubmissionAllowed($draft);
        $stored = $repository->getResource($draft);
        $this->synchronizeSystemFields();
        $fields = $this->form->validateForUpdate($this->draftRevision, $repository->draftRevision($stored));
        $encounter = $this->validateDraftEncounter($draft->person);
        $addresses = $this->draftAddresses($draft->person, $encounter);
        $authorUuid = $user->getCarePlanWriterEmployee($this->form->termsOfService)?->uuid
            ?? data_get($stored, 'author.identifier.value');
        if ($authorUuid === null) {
            throw ValidationException::withMessages(['form.author' => __('care-plan.referral_api_no_active_employee')]);
        }

        $mapper = objectMapper();
        $data = $mapper->map($this->form, CarePlanData::class);
        $data->prepareDraftUpdate($stored, $fields, $authorUuid, $addresses, $mapper);
        $saved = $repository->saveDraft($data, $draft, $mapper);

        return $repository->draftRevision($repository->getResource($saved));
    }

    public function delete(): void
    {
        $carePlan = $this->carePlan;
        if (Auth::user()?->can('update', $carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        $encounter = $carePlan->encounter;
        if (!Repository::carePlan()->deleteDraft($carePlan->id)) {
            Session::flash('error', __('Можна видаляти лише чернетки планів лікування.'));

            return;
        }

        Session::flash('success', __('Чернетку плану лікування успішно видалено.'));
        if ($encounter !== null) {
            $this->redirectRoute('persons.encounters.package.show', [legalEntity(), $carePlan->personId, $encounter->id], navigate: true);

            return;
        }

        $this->redirectRoute('persons.care-plans', [legalEntity(), $carePlan->personId], navigate: true);
    }

    #[Computed]
    public function carePlan(): CarePlan
    {
        return Repository::carePlan()->findById($this->carePlanId)
            ?? throw (new ModelNotFoundException())->setModel(CarePlan::class, [$this->carePlanId]);
    }

    public function render(): View
    {
        return view('livewire.care-plan.care-plan-create', ['carePlan' => $this->carePlan]);
    }
}
