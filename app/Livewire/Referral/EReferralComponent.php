<?php

declare(strict_types=1);

namespace App\Livewire\Referral;

use App\Dto\ServiceRequest\Form as ReferralFormData;
use App\Livewire\Referral\Forms\EReferralForm;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;

/** Shared form state; page access belongs to the route, and mutations authorize their own actions. */
abstract class EReferralComponent extends Component
{
    public EReferralForm $eReferralForm;

    #[Locked]
    public int $legalEntityId;

    #[Locked]
    public int $personId;

    #[Locked]
    public int $referralId;

    protected ?ServiceRequestRequest $referralModel = null;

    protected function initializeReferral(int $legalEntityId, int $personId, int $referralId): void
    {
        $this->legalEntityId = $legalEntityId;
        $this->personId = $personId;
        $this->referralId = $referralId;

        $data = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor())
            ->map($this->referral(), ReferralFormData::class);
        $this->eReferralForm->setReferral($data);
    }

    /** Resolve in the locked patient context on every request, including after Livewire hydration. */
    protected function referral(): ServiceRequestRequest
    {
        abort_unless((int) legalEntity()->id === $this->legalEntityId, 404);

        return $this->referralModel ??= Repository::serviceRequest()->findForPerson($this->referralId, $this->personId);
    }

    protected function referralViewData(): array
    {
        $record = $this->referral();
        // Persistent page middleware and this projection both respect access changes after the initial request.
        abort_if(Auth::user()->cannot('view', $record), 404);

        return [
            'referral' => $record,
            'summary' => Repository::serviceRequest()->toPatientRegistryRow($record),
            'resource' => $record->detailsSyncedAt !== null ? Repository::serviceRequest()->getResource($record) : [],
            'patientFullName' => $record->person->fullName,
        ];
    }
}
