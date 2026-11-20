<?php

declare(strict_types=1);

namespace App\Livewire\Referral;

use App\Livewire\Referral\EReferralComponent;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use Illuminate\Contracts\View\View;

class EReferralView extends EReferralComponent
{
    public function mount(LegalEntity $legalEntity, Person $person, ServiceRequestRequest $serviceRequest): void
    {
        $this->initializeReferral($legalEntity->id, $person->id, $serviceRequest->id);
    }

    public function render(): View
    {
        return view('livewire.referral.e-referral-view', $this->referralViewData());
    }
}
