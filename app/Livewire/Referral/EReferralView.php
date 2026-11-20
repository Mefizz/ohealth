<?php

declare(strict_types=1);

namespace App\Livewire\Referral;

use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EReferralView extends Component
{
    #[Locked]
    public int $legalEntityId;

    #[Locked]
    public int $personId;

    #[Locked]
    public int $referralId;

    public function mount(LegalEntity $legalEntity, Person $person, ServiceRequestRequest $serviceRequest): void
    {
        $this->legalEntityId = $legalEntity->id;
        $this->personId = $person->id;
        $this->referralId = $serviceRequest->id;
    }

    public function render(): View
    {
        $legalEntity = legalEntity();
        abort_unless($legalEntity?->id === $this->legalEntityId, 404);

        $repository = Repository::serviceRequest();
        $referral = $repository->findForPerson($this->referralId, $this->personId);
        $patient = $referral->person;
        abort_if($patient === null || Auth::user()->cannot('view', [$referral, $patient, $legalEntity]), 404);

        return view('livewire.referral.e-referral-view', [
            'patientFullName' => $patient->fullName,
            'referral' => $referral,
            'legalEntity' => $legalEntity,
            'resource' => $referral->detailsSyncedAt !== null ? $repository->getResource($referral) : [],
            'summary' => $repository->toPatientRegistryRow($referral),
        ]);
    }
}
