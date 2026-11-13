<?php

declare(strict_types=1);

namespace App\Livewire\EncounterPackage;

use App\Enums\Person\EncounterStatus;
use App\Livewire\EncounterPackage\EncounterPackageEdit;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Models\Preperson;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class EncounterPackageView extends EncounterPackageEdit
{
    /**
     * Edit page of a draft that is not waiting for eHealth.
     *
     * @var string|null
     */
    #[Locked]
    public ?string $editUrl = null;

    /**
     * Load the encounter package for viewing, offering its edit page while it can still be changed.
     *
     * @param  LegalEntity  $legalEntity
     * @param  Encounter  $encounter
     * @param  Person|null  $person
     * @param  Preperson|null  $preperson
     * @return void
     */
    public function mount(LegalEntity $legalEntity, Encounter $encounter, ?Person $person = null, ?Preperson $preperson = null): void
    {
        parent::mount($legalEntity, $encounter, $person, $preperson);

        // The edit page leaves only a draft that is not waiting for eHealth editable
        $this->editUrl = $this->isReadonly ? null : $this->editPageUrl();

        $this->isReadonly = true;
    }

    /**
     * Offer the additional actions once the submitted encounter was processed and finished.
     *
     * @return void
     */
    #[On('submission-processed')]
    public function showAdditionalActions(): void
    {
        $this->isFinished = Encounter::whereKey($this->encounterId)->value('status') === EncounterStatus::FINISHED;
        if ($this->isFinished) {
            $this->promptReferralCompletion();
        }
    }

    /**
     * Offer the edit page once eHealth rejected the submitted package, which stays a draft to fix.
     *
     * @return void
     */
    #[On('submission-rejected')]
    public function offerEditing(): void
    {
        $this->editUrl = $this->editPageUrl();
    }

    /**
     * Get the edit page of the encounter.
     *
     * @return string
     */
    private function editPageUrl(): string
    {
        return route(
            $this->prepersonId !== null ? 'prepersons.encounters.edit' : 'persons.encounters.edit',
            $this->prepersonId !== null
                ? [legalEntity(), 'preperson' => $this->prepersonId, 'encounter' => $this->encounterId]
                : [legalEntity(), 'person' => $this->personId, 'encounter' => $this->encounterId]
        );
    }

    /**
     * Include issued referrals, the incoming referral and referrals referenced by the encounter's EMRs.
     * context_id is a FHIR Identifier FK, so match its UUID value rather than the local encounter ID.
     */
    #[Computed]
    public function electronicReferrals(): array
    {
        $user = Auth::user();
        $legalEntity = legalEntity();
        if ($this->personId === null || $user === null || $legalEntity === null || $user->cannot('search', ServiceRequestRequest::class)
            || $user->cannot('view', Person::class)) {
            return [];
        }

        $patient = $this->patient();
        $encounter = Encounter::forPatient($patient)->whereKey($this->encounterId)
            ->with('incomingReferral')->firstOrFail();
        if ($user->cannot('view', [$encounter, $patient])) {
            return [];
        }

        return ServiceRequestRequest::readableBy($user, $patient, $legalEntity)
            ->relatedToEncounter($encounter, $patient, $user, $legalEntity)->forRegistry()
            ->latest('id')->get()
            ->unique('uuid')
            ->map(fn (ServiceRequestRequest $request): array => Repository::serviceRequest()->toPatientRegistryRow($request))
            ->values()->all();
    }

    /**
     * Render the encounter package form, which is readonly here.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.encounter-package.encounter-package-view', [
            'electronicReferrals' => $this->electronicReferrals,
        ]);
    }
}
