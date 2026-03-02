<?php

namespace App\Livewire\TreatmentPlan;

use App\Classes\eHealth\EHealth;
use App\Exceptions\eHealth\EHealthResponseException;
use App\Exceptions\eHealth\EHealthValidationException;
use App\Jobs\CarePlanSync;
use App\Livewire\TreatmentPlan\Forms\TreatmentPlanForm;
use App\Models\TreatmentPlan\TreatmentPlan;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Core\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

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

    public function sign(): void
    {
        // 1. Validate General Form
        $this->validate($this->form->rulesForSave($this), [], $this->form->validationAttributes());
        
        // 2. Validate KEP Fields
        $validatedCipher = $this->validate($this->form->rulesForKepOnly());
        
        // 3. Prepare payload for local saving & API
        $preparedData = $this->form->getPreparedData();
        $preparedData['patient_id'] = '123e4567-e89b-12d3-a456-426614174000'; // mocked for Epic 1.3
        $preparedData['uuid'] = Str::uuid()->toString();
        $preparedData['status'] = 'NEW';
        
        // Save to local DB first 
        TreatmentPlan::create($preparedData);

        // 4. Format the required EHealth JSON schema
        // Note: Formatting is handled by the component for Epic 1.3 until Repository exists
        $formattedData = $this->formatEHealthRequest($preparedData);

        // 5. Sign the payload using signatureService
        try {
            $taxId = Auth::user()?->party->taxId ?? '1111111111'; // Mock fallback for testing
            
            $signedContent = signatureService()->signData(
                Arr::toSnakeCase($formattedData),
                $validatedCipher['password'],
                $validatedCipher['knedp'],
                $validatedCipher['keyContainerUpload'],
                $taxId
            );

            // 6. Send to API and capture response
            $response = EHealth::carePlan()->create($preparedData['patient_id'], ['signed_data' => $signedContent]);

            // If an async job is returned, extract the ID
            $jobId = $response->json('data.id') ?? $response->json('urgent.id') ?? null;
            
            // 7. Update local record
            $treatmentPlan = TreatmentPlan::where('uuid', $preparedData['uuid'])->first();
            if ($treatmentPlan) {
                $treatmentPlan->update([
                    'status' => 'PROCESSING',
                    'job_id' => $jobId
                ]);

                if ($jobId) {
                    CarePlanSync::dispatch($treatmentPlan)->delay(now()->addSeconds(5));
                }
            }

            Session::flash('success', __('treatment-plan.saved_successfully'));
            // Reset form or redirect...
        } catch (\Exception $exception) {
            // Simple generic exception catch for now
            Session::flash('error', 'Помилка EHealth: ' . $exception->getMessage());
        }
    }

    private function formatEHealthRequest(array $data): array
    {
        // As per specification 3.10.1 CarePlan creation mapping:
        return [
            'id' => $data['uuid'],
            'status' => 'active', // Should it be new or active in EHealth? Spec: 'status is NEW but KEP requires...'
            'intent' => $data['intention'],
            'title' => $data['name_treatment_plan'],
            'description' => $data['description'] ?? null,
            'category' => [
                'coding' => [
                    [
                        'system' => 'eHealth/care_plan_categories',
                        'code' => $data['category']
                    ]
                ]
            ],
            'subject' => [
                'identifier' => [
                    'type' => [
                        'coding' => [
                            ['system' => 'eHealth/resources', 'code' => 'patient']
                        ]
                    ],
                    'value' => $data['patient_id']
                ]
            ],
            'period' => [
                'start' => isset($data['period_start']) ? \Carbon\Carbon::parse($data['period_start'])->toIso8601String() : null,
                'end' => isset($data['period_end']) ? \Carbon\Carbon::parse($data['period_end'])->toIso8601String() : null,
            ]
        ];
    }
}
