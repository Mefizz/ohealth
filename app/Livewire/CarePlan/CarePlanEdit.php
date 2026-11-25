<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Dto\CarePlan\Form as CarePlanFormData;
use App\Dto\CarePlan\Model as CarePlanData;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Repositories\Repository;
use App\Services\MedicalEvents\Fhir;
use ArrayObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class CarePlanEdit extends CarePlanCreate
{
    #[Locked]
    public int $carePlanId;

    #[Locked]
    public string $draftRevision;

    public function mount(LegalEntity $legalEntity, $personId = null, $encounter = null, $carePlan = null): void
    {
        $bound = $carePlan ?? request()->route('carePlan');
        $repository = Repository::carePlan();
        $id = $bound instanceof CarePlan ? $bound->id : (int) $bound;
        $this->carePlanId = $id;
        $carePlan = $this->carePlan;

        if ($repository->hasPendingSubmission($this->carePlan)) {
            Session::flash('info', __('care-plan.cannot_edit_non_draft'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }
        if ($this->carePlan->requiresFhirImport()) {
            Session::flash('warning', __('care-plan.draft_backfill_required'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }

        $this->loadPatientForm($legalEntity, $carePlan->personId, $carePlan->person);
        $resource = $repository->getResource($this->carePlan->id);
        $this->draftRevision = $repository->draftRevision($resource);
        $fields = Fhir::carePlan()->fromFhir($resource, ['timezone' => config('app.timezone'), 'dateFormat' => config('app.date_format')]);
        $mapper = objectMapper();
        $formData = $mapper->map(new ArrayObject($fields), CarePlanFormData::class);
        $mapper->map($formData, $this->form);
        $this->form->episodes = $repository->resolveEpisodeLabels($this->form->episodes, $this->personId);
        $this->carePlanUuid = $resource['id'];
        $this->patientUuid = $this->carePlan->person->uuid;
        $this->form->patient = $this->carePlan->person->fullName ?? '';
        $this->medicalNumber = (string) ($this->carePlan->encounterId ?? '');
        $this->synchronizeSystemFields();
        if ($this->carePlan->encounter) {
            $this->diagnoses = $this->buildDiagnosesForUi($this->carePlan->encounter);
        }
        $this->refreshCategoryOptions();
    }

    public function save(bool $openForSigning = false): void
    {
        $user = Auth::user();
        if ($user?->can('update', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        if ($openForSigning && $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_create'));

            return;
        }

        try {
            $this->synchronizeSystemFields();
            $fields = $this->form->validate($this->form->rulesForDraftEdit());
            $repository = Repository::carePlan();
            $stored = $repository->getResource($this->carePlan->id);
            $mapper = objectMapper();
            $draft = $this->prepareEditedDraft($stored, $fields, $mapper);
            $repository->updateDraft($this->carePlan, $draft, $this->draftRevision, $mapper);
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return;
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());

            return;
        }

        if ($openForSigning) {
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id, 'action' => 'sign_plan'], navigate: true);

            return;
        }

        Session::flash('success', __('care-plan.draft_updated'));
        $this->redirectRoute('care-plans.edit', [legalEntity(), $this->carePlan->id], navigate: true);
    }

    public function startSigningProcess(): void
    {
        $this->save(openForSigning: true);
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

    protected function prepareEditedDraft(array $stored, array $fields, ObjectMapperInterface $mapper): CarePlanData
    {
        $repository = Repository::carePlan();
        $user = Auth::user();
        $person = $this->carePlan->person;
        $encounter = $repository->findEncounter($this->form->encounter, $person->id, forDraftCreation: true);
        if ($encounter === null || $user?->can('view', [$encounter, $person]) !== true) {
            throw ValidationException::withMessages(['form.encounter' => __('validation.exists', ['attribute' => __('care-plan.encounter')])]);
        }
        $repository->assertCategoryForEncounter($this->form->category, $encounter->uuid, $person->id);
        $addresses = $repository->encounterData($encounter->uuid, $person->id, $this->conditionUuid ?: null)['addresses'];
        if ($addresses === []) {
            throw ValidationException::withMessages(['form.category' => __('care-plan.category_diagnosis_unavailable')]);
        }

        $author = $user->getCarePlanWriterEmployee($this->form->termsOfService);
        $authorUuid = $author?->uuid ?? data_get($stored, 'author.identifier.value');
        if ($authorUuid === null) {
            throw ValidationException::withMessages(['form.author' => __('care-plan.referral_api_no_active_employee')]);
        }

        $draft = $mapper->map($this->form, CarePlanData::class);
        $draft->prepareDraftUpdate($stored, $fields, $authorUuid, $addresses, $mapper);

        return $draft;
    }

    #[Computed]
    public function carePlan(): CarePlan
    {
        return Repository::carePlan()->findById($this->carePlanId)
            ?? throw (new ModelNotFoundException())->setModel(CarePlan::class, [$this->carePlanId]);
    }

    public function render()
    {
        return view('livewire.care-plan.care-plan-create', ['carePlan' => $this->carePlan]);
    }
}
