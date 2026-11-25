<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Enums\CarePlanStatus;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Repositories\CarePlanRepository;
use App\Repositories\Repository;
use App\Services\MedicalEvents\Fhir;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

class CarePlanUpdate extends CarePlanCreate
{
    #[Locked]
    public CarePlan $carePlan;

    public function hydrate(): void
    {
        abort_unless(Auth::user()->can('view', $this->carePlan), 404);
        $this->carePlan = Repository::carePlan()->findById($this->carePlan->id);
    }

    public function mount(LegalEntity $legalEntity, $personId = null, $encounter = null, $carePlan = null): void
    {
        $bound = $carePlan ?? request()->route('carePlan');
        $repository = Repository::carePlan();
        $carePlan = $repository->findById($bound instanceof CarePlan ? $bound->id : (int) $bound);
        abort_if($carePlan === null, 404);
        $this->carePlan = $carePlan;
        $this->authorize('view', $this->carePlan);
        $this->authorize('manage', $this->carePlan);
        if ($repository->hasPendingSubmission($this->carePlan) || CarePlanStatus::fromStored($this->carePlan->status) !== CarePlanStatus::DRAFT) {
            Session::flash('info', __('care-plan.cannot_edit_non_draft'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }
        $this->authorize('update', $this->carePlan);
        if ($this->carePlan->requiresFhirImport()) {
            Session::flash('warning', __($this->carePlan->status === CarePlanStatus::DRAFT->value ? 'care-plan.draft_backfill_required' : 'care-plan.full_sync_required'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }

        parent::mount($legalEntity, $this->carePlan->personId);
        $resource = $repository->getResource($this->carePlan->id);
        $fields = Fhir::carePlan()->fromFhir($resource, ['timezone' => config('app.timezone'), 'dateFormat' => config('app.date_format')]);
        foreach ($fields as $field => $value) {
            if (property_exists($this->form, $field)) {
                $this->form->{$field} = $value ?? '';
            }
        }
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

    public function save(CarePlanRepository $repository): void
    {
        $draft = $this->saveDraftChanges($repository);
        if ($draft !== null) {
            Session::flash('success', __('care-plan.draft_updated'));
            $this->redirectRoute('care-plans.edit', [legalEntity(), $draft->id], navigate: true);
        }
    }

    protected function saveDraftChanges(CarePlanRepository $repository): ?CarePlan
    {
        $user = Auth::user();
        $draft = $this->carePlan;
        if ($user?->can('update', $draft) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return null;
        }

        try {
            $this->synchronizeSystemFields();
            $fields = $this->form->validate([
                ...$this->form->rules(),
                'coAuthors' => 'array',
                'coAuthors.*' => 'nullable|uuid',
                'periodStartTime' => 'nullable|date_format:H:i,H:i:s',
                'periodEndTime' => 'nullable|date_format:H:i,H:i:s',
            ]);
            $encounter = $this->validateDraftEncounter($draft->person);
            $addresses = $this->draftAddresses($draft->person, $encounter);
            $authorUuid = $user->getCarePlanWriterEmployee($this->form->termsOfService)?->uuid
                ?? data_get($repository->getResource($draft), 'author.identifier.value');
            if ($authorUuid === null) {
                throw ValidationException::withMessages(['form.author' => __('care-plan.referral_api_no_active_employee')]);
            }

            $fields['coAuthors'] = array_values(array_filter($fields['coAuthors']));
            $repository->assertFhirStorageReady();

            return $repository->saveForm([
                ...$fields,
                'author' => $authorUuid,
                'addresses' => $addresses,
            ], $draft->personId, $draft->legalEntityId, $draft->id);
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return null;
    }

    public function delete(CarePlanRepository $repository): void
    {
        $this->authorize('update', $this->carePlan);
        if (isset($this->carePlan) && $this->carePlan->exists) {
            if ($repository->deleteDraft($this->carePlan->id)) {
                session()->flash('success', __('Чернетку плану лікування успішно видалено.'));
            } else {
                session()->flash('error', __('Можна видаляти лише чернетки планів лікування.'));
            }
        }

        $encounter = $this->carePlan->encounter;
        if ($encounter) {
            $this->redirectRoute('persons.encounters.package.show', [legalEntity(), $this->personId, $encounter->id], navigate: true);

            return;
        }

        $this->redirectRoute('persons.care-plans', [legalEntity(), $this->personId], navigate: true);
    }

    public function sign(CarePlanRepository $repository, string $keyContainer = '', string $password = ''): void
    {
        $user = Auth::user();
        if ($user?->can('update', $this->carePlan) !== true || $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        $credentials = $this->signingCredentials($keyContainer, $password);
        if ($credentials === null) {
            return;
        }

        $draft = $this->saveDraftChanges($repository);
        if ($draft !== null) {
            $this->submitDraft($repository, $draft, $credentials, $user);
        }
    }

    public function render(): View
    {
        // Reuse the same view as Create
        return view('livewire.care-plan.care-plan-create');
    }
}
