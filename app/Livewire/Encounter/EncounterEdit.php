<?php

declare(strict_types=1);

namespace App\Livewire\Encounter;

use App\Core\Arr;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Repositories\MedicalEvents\Repository;
use App\Services\MedicalEvents\Mappers\ConditionMapper;
use App\Services\MedicalEvents\Mappers\EncounterMapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

class EncounterEdit extends EncounterComponent
{
    #[Locked]
    public int $encounterId;

    public function mount(LegalEntity $legalEntity, int $personId, int $encounterId): void
    {
        $this->initializeComponent($personId);
        $this->encounterId = $encounterId;

        $encounter = Encounter::withRelationships()->whereId($encounterId)->firstOrFail()->toArray();

        $this->form->encounter = app(EncounterMapper::class)->fromFhir($encounter);

        $episodeUuid = data_get($encounter, 'episode.identifier.value', '');

        $this->episodeType = 'existing';
        $this->form->episode['id'] = $episodeUuid;

        $conditions = Repository::condition()->getByUuids(
            collect(data_get($encounter, 'diagnoses', []))
                ->pluck('condition.identifier.value')
                ->filter()
                ->values()
                ->toArray()
        );

        $detailsMap = Repository::condition()->getDetailsMapForEvidences($conditions);

        $this->form->conditions = collect($conditions)
            ->map(fn (array $condition) => app(ConditionMapper::class)->fromFhir($condition, $detailsMap))
            ->toArray();

        //        $this->form->immunizations = Repository::immunization()->get($this->encounterId);
        //        $this->form->immunizations = Repository::immunization()->formatForView($this->form->immunizations);
        //
        //        $this->form->diagnosticReports = Repository::diagnosticReport()->get($this->encounterId);
        //        $this->form->diagnosticReports = Repository::diagnosticReport()->formatForView($this->form->diagnosticReports);
        //
        //        $this->form->observations = Repository::observation()->get($this->encounterId);
        //        $this->form->observations = Repository::observation()->formatForView($this->form->observations);
        //
        //        $this->form->procedures = Repository::procedure()->get($this->encounterId);
        //        $this->form->procedures = Repository::procedure()->formatForView($this->form->procedures);
        //
        //        $this->form->clinicalImpressions = Repository::clinicalImpression()->get($this->encounterId);
    }

    /**
     * Validate and update data.
     *
     * @return void
     * @throws Throwable
     */
    public function save(): void
    {
        try {
            $validated = $this->form->validate();
        } catch (ValidationException $exception) {
            Session::flash('error', $exception->validator->errors()->first());
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        // format to fhir format for local saving(updating)
        $encounter = Encounter::withRelationships()->whereId($this->encounterId)->firstOrFail();
        $uuids = [
            'encounter' => $encounter->uuid,
            'visit' => data_get($encounter->toArray(), 'visit.identifier.value'),
            'employee' => Auth::user()->getEncounterWriterEmployee()->uuid,
            'episode' => $validated['episode']['id']
        ];
        $fhirConditions = collect($validated['conditions'] ?? [])
            ->map(fn (array $condition) => app(ConditionMapper::class)->toFhir($condition, $uuids))
            ->values()
            ->toArray();
        $formattedEncounter = app(EncounterMapper::class)->toFhir(
            $validated['encounter'],
            $fhirConditions,
            $uuids
        );

        // map id to uuid for using sync method
        $conditionsSyncData = collect($fhirConditions)->map(
            fn (array $item) => collect($item)->put('uuid', $item['id'])->forget(['id'])->all()
        )->toArray();
        $encounterSyncData = collect($formattedEncounter)
            ->put('uuid', $formattedEncounter['id'])
            ->forget(['id'])
            ->all();

        Repository::encounter()->sync($this->personId, [Arr::toSnakeCase($encounterSyncData)]);
        Repository::condition()->sync($this->personId, Arr::toSnakeCase($conditionsSyncData));

        Session::flash('success', 'Взаємодія успішно оновлена.');
    }
}
