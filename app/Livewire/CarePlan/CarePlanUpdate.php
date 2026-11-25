<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan;

use App\Classes\eHealth\EHealth;
use App\Dto\CarePlan\Form as CarePlanFormData;
use App\Enums\CarePlanStatus;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Exceptions\MedicalEvents\ClinicalStorageException;
use App\Exceptions\Signature\SignatureException;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Repositories\CarePlanRepository;
use App\Repositories\Repository;
use App\Services\MedicalEvents\Fhir;
use App\Services\MedicalEvents\FhirResource;
use ArrayObject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

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
        $user = Auth::user();
        abort_unless($user?->can('view', $this->carePlan) && $user->can('update', $this->carePlan), 404);
        if ($repository->hasPendingSubmission($this->carePlan) || CarePlanStatus::fromStored($this->carePlan->status) !== CarePlanStatus::DRAFT) {
            Session::flash('info', __('care-plan.cannot_edit_non_draft'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }
        if ($this->carePlan->requiresFhirImport()) {
            Session::flash('warning', __($this->carePlan->status === CarePlanStatus::DRAFT->value ? 'care-plan.draft_backfill_required' : 'care-plan.full_sync_required'));
            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
            $this->skipRender();

            return;
        }

        parent::mount($legalEntity, $this->carePlan->personId);
        $resource = $repository->getResource($this->carePlan->id);
        $fields = Fhir::carePlan()->fromFhir($resource, ['timezone' => config('app.timezone'), 'dateFormat' => config('app.date_format')]);
        $mapper = objectMapper();
        $formData = $mapper->map(new ArrayObject($fields), CarePlanFormData::class);
        $mapper->map($formData, $this->form);
        $this->form->episodes = $repository->resolveEpisodeLabels($this->form->episodes, $this->personId);
        $this->carePlanUuid = $resource['id'];
        $this->patientUuid = $this->carePlan->person->uuid;
        $this->form->patient = $this->carePlan->person->fullName ?? '';
        $this->form->medicalNumber = (string) ($this->carePlan->encounterId ?? '');
        $this->synchronizeSystemFields();
        if ($this->carePlan->encounter) {
            $this->diagnoses = $this->buildDiagnosesForUi($this->carePlan->encounter);
        }
        $this->refreshCategoryOptions();
    }

    /**
     * Update existing local draft.
     */
    public function save(): void
    {
        if (Auth::user()?->cannot('update', $this->carePlan)) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        $repository = Repository::carePlan();
        try {
            $repository->assertDraftSubmissionAllowed($this->carePlan);
            $this->synchronizeSystemFields();
            $this->form->validate();
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return;
        }

        $encounterData = $this->resolveEncounterData();

        // Re-resolve the author for the (possibly changed) terms_of_service, same as on create,
        // so a draft edited to a different "умови надання послуг" keeps a matching author.
        $author = Auth::user()?->getCarePlanWriterEmployee($this->form->termsOfService ?: null);

        try {
            $repository->assertFhirStorageReady();
            $repository->saveForm([
                'author_id' => $author?->id ?? $this->carePlan->authorId,
                'category' => $this->form->category,
                'co_authors' => $this->form->coAuthors,
                'context' => $this->form->context ?: null,
                'title' => $this->form->title,
                'terms_of_service' => $this->form->termsOfService ?: null,
                'period_start' => $this->form->periodStartIsoDate(),
                'period_start_time' => $this->form->periodStartTime,
                'period_end_time' => $this->form->periodEndTime,
                'period_end' => $this->form->periodEndIsoDate(),
                'encounter_id' => $encounterData['id'],
                'addresses' => $encounterData['addresses'],
                'supporting_info' => [
                    'episodes' => $this->form->episodes,
                    'medical_records' => $this->form->medicalRecords,
                ],
                'description' => $this->form->description ?: null,
                'note' => $this->form->note ?: null,
                'inform_with' => $this->form->informWith ?: null,
            ], $this->carePlan->personId, legalEntity()->id, $this->carePlan->id, draftOnly: true);
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return;
        } catch (ClinicalStorageException $exception) {
            Session::flash('error', $exception->getMessage());

            return;
        }

        session()->flash('success', __('care-plan.draft_updated') ?? 'План лікування успішно збережено');

        $this->redirectRoute('care-plans.edit', [legalEntity(), $this->carePlan->id], navigate: true);
    }

    public function delete(CarePlanRepository $repository): void
    {
        if (Auth::user()?->can('update', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }
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

    /**
     * Start the signing process by opening the signature modal.
     */
    public function startSigningProcess(): void
    {
        if (Auth::user()?->can('update', $this->carePlan) !== true) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        try {
            Repository::carePlan()->assertDraftSubmissionAllowed($this->carePlan);
            $this->synchronizeSystemFields();
            $this->form->validate();
            $this->validateCategoryForSigning();
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);

            return;
        }

        $this->showSignatureModal = true;
    }

    /**
     * Sign with KEP and send to eHealth (Update current plan).
     */
    public function sign(string $keyContainer = '', string $password = ''): void
    {
        $user = Auth::user();
        if ($user === null || $user->cannot('update', $this->carePlan) || $user->cannot('create', CarePlan::class)) {
            Session::flash('error', __('care-plan.no_permission_update'));

            return;
        }

        $repository = Repository::carePlan();
        $this->form->useSigningSecrets($keyContainer, $password);

        try {
            $repository->assertDraftSubmissionAllowed($this->carePlan);
            $this->synchronizeSystemFields();
            $validated = $this->form->validate($this->form->rulesForSigning());
            $this->validateCategoryForSigning();

            $encounter = $this->resolveEncounterData();
            $author = $user->getCarePlanWriterEmployee($this->form->termsOfService);
            if ($author?->uuid === null) {
                throw ValidationException::withMessages(['form.author' => __('care-plan.referral_api_no_active_employee')]);
            }

            $patientUuid = $this->carePlan->person->uuid;
            $stored = $repository->getResource($this->carePlan->id);
            $draft = $this->draftResourceForSigning($stored, $author->uuid, $encounter['addresses']);
            $api = EHealth::carePlan();
            $signature = signatureService()->signData(
                $api->prepareCreateContent($draft),
                $validated['knedp'],
                $validated['keyContainer'],
                $validated['password'],
                $user->party->taxId
            );

            $result = $this->submitClinicalOperation(
                $this->carePlan,
                ['status' => CarePlanStatus::PENDING->value],
                fn () => $api->create($patientUuid, [
                    'signed_data' => $signature,
                    'signed_data_encoding' => 'base64',
                ], $this->carePlan->uuid),
                operationContext: ['expected' => $stored, 'draft' => $draft]
            );

            if ($result !== null) {
                Session::flash('success', __('care-plan.signed_and_sent'));
            }

            $this->redirectRoute('care-plans.show', [legalEntity(), $this->carePlan->id], navigate: true);
        } catch (ValidationException $exception) {
            $this->handleValidationFailed($exception);
        } catch (SignatureException $exception) {
            $exception->handle('Error when signing care plan');
        } catch (EHealthConnectionException $exception) {
            Session::flash('error', __('care-plan.connection_error'));
        } catch (EHealthValidationException|EHealthResponseException $exception) {
            Session::flash('error', $exception instanceof EHealthValidationException
                ? $exception->getFormattedMessage()
                : __('care-plan.ehealth_error_prefix').$exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('error', $exception instanceof ClinicalStorageException
                ? $exception->getMessage()
                : __('care-plan.unexpected_error'));
        }
    }

    /** Apply the validated editor fields to the local draft snapshot before signing. */
    protected function draftResourceForSigning(array $stored, string $authorUuid, array $addresses): array
    {
        $fields = $this->form->only([
            'title', 'category', 'termsOfService', 'periodStart', 'periodStartTime', 'periodEnd',
            'periodEndTime', 'description', 'note', 'context', 'encounter', 'informWith',
            'coAuthors', 'episodes', 'medicalRecords',
        ]);

        Validator::make($fields, [
            'medicalRecords.*' => 'array',
            'coAuthors.*' => 'nullable|uuid',
            'episodes.*' => 'array',
            'episodes.*.uuid' => 'required|uuid',
        ])->validate();

        $fields['medicalRecords'] = collect($fields['medicalRecords'])->map(static function (array $reference): array {
            if (isset($reference['identifier'])) {
                return $reference;
            }

            Validator::make($reference, [
                'uuid' => 'required|uuid',
                'type' => 'required|in:procedure,diagnostic_report',
            ])->validate();

            return FhirResource::make()->coding('eHealth/resources', $reference['type'])->toIdentifier($reference['uuid']);
        })->all();
        $fields['coAuthors'] = array_values(array_filter($fields['coAuthors']));
        $fields['author'] = $authorUuid;
        $fields['addresses'] = $addresses;

        return Fhir::carePlan()->toFhir($fields, [
            'resource' => $stored,
            'timezone' => config('app.timezone'),
            'dateFormat' => config('app.date_format'),
        ]);
    }

    public function render()
    {
        // Reuse the same view as Create
        return view('livewire.care-plan.care-plan-create');
    }
}
