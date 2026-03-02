<?php

namespace App\Livewire\TreatmentPlan;

use App\Livewire\TreatmentPlan\Forms\TreatmentPlanForm;
use App\Models\TreatmentPlan\TreatmentPlan;
use Livewire\Component;
use Illuminate\Support\Str;

class TreatmentPlanCreate extends Component
{
    public TreatmentPlanForm $form;

    public function mount(): void
    {
        // Add default values to prevent undefined array key warnings
        $this->form->period['during']['startDate'] = '';
        $this->form->period['during']['startTime'] = '';
        $this->form->period['during']['endDate'] = '';
        $this->form->period['during']['endTime'] = '';
    }

    public function render()
    {
        return view('livewire.treatment-plan.treatment-plan-create');
    }

    public function createLocally(): void
    {
        $this->save();
    }

    public function create(): void
    {
        // For now, normal save. Epic 1.3 will implement API payload sending here.
        $this->save();
    }

    public function save(): void
    {
        $this->validate($this->form->rulesForSave($this), [], $this->form->validationAttributes());
        
        // Data ready for repository/database
        $preparedData = $this->form->getPreparedData();

        // Save to local DB with status NEW
        $preparedData['status'] = 'NEW';
        $preparedData['patient_id'] = '123e4567-e89b-12d3-a456-426614174000'; // mocked patient id for now
        $preparedData['uuid'] = Str::uuid()->toString();

        TreatmentPlan::create($preparedData);

        session()->flash('message', __('treatment-plan.saved_successfully'));

        // Reset form or redirect
        $this->form->reset();
    }
}
