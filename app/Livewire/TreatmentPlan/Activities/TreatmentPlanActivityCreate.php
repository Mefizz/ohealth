<?php

namespace App\Livewire\TreatmentPlan\Activities;

use App\Models\TreatmentPlan\TreatmentPlanActivity;
use Livewire\Component;
use Illuminate\Support\Facades\Session;

class TreatmentPlanActivityCreate extends Component
{
    public $treatmentPlanId;
    
    // Form fields - General
    public $detail_kind = 'medication_request'; // medication_request, service_request, device_request
    public $medical_program_id = '';
    public $funding_source = '';
    public $instruction = ''; // Текст/Опис

    // Form fields - Basic Data (Основні дані)
    public $category = ''; // For services
    public $code = ''; // МНН / Код послуги / Код виробу
    public $quantity_value = 1;
    public $quantity_system = '';
    public $quantity_code = 'шт'; // упаковки, дози, шт
    
    // Period / Course (Період / Курс лікування)
    public $period_start = '';
    public $period_end = '';
    
    // Dosage (Дозування для ліків)
    public $dose_value = '';
    public $frequency = '';

    // Reasons (Підстави)
    public $reason_condition_id = ''; // Діагноз/Стан

    // UI state
    public $showModal = false;

    protected $rules = [
        'detail_kind' => 'required|in:medication_request,service_request,device_request',
        'code' => 'required|string',
        'quantity_value' => 'required|integer|min:1',
        'period_start' => 'nullable|date',
        'period_end' => 'nullable|date|after_or_equal:period_start',
        'instruction' => 'nullable|string|max:1000',
    ];

    public function mount($treatmentPlanId)
    {
        $this->treatmentPlanId = $treatmentPlanId;
        $this->period_start = now()->format('Y-m-d');
    }

    public function openModal($kind = 'medication_request')
    {
        $this->resetValidation();
        $this->reset(['medical_program_id', 'code', 'category', 'funding_source', 'reason_condition_id', 'dose_value', 'frequency', 'instruction']);
        $this->quantity_value = 1;
        $this->quantity_code = 'шт';
        $this->period_start = now()->format('Y-m-d');
        $this->detail_kind = $kind;
        $this->showModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
    }

    public function saveActivity()
    {
        $this->validate();

        // Construct timing JSON from period and frequency
        $timing = [
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'frequency' => $this->frequency,
            'dose' => $this->dose_value
        ];

        TreatmentPlanActivity::create([
            'treatment_plan_id' => $this->treatmentPlanId,
            'detail_kind' => $this->detail_kind,
            'medical_program_id' => $this->medical_program_id ?: null,
            'funding_source' => $this->funding_source ?: null,
            'category' => $this->category ?: null,
            'reason_condition_id' => $this->reason_condition_id ?: null,
            'code' => $this->code,
            'quantity_value' => $this->quantity_value ?: null,
            'quantity_system' => $this->quantity_system ?: null,
            'quantity_code' => $this->quantity_code ?: null,
            'instruction' => $this->instruction ?: null,
            'timing' => $timing,
            'status' => 'NEW'
        ]);

        $this->closeModal();
        Session::flash('success', __('Призначення успішно збережено.'));
        $this->dispatch('activity-added');
    }

    public function render()
    {
        return view('livewire.treatment-plan.activities.treatment-plan-activity-create');
    }
}
