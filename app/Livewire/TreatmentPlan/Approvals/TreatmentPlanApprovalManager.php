<?php

namespace App\Livewire\TreatmentPlan\Approvals;

use App\Classes\eHealth\EHealth;
use Livewire\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class TreatmentPlanApprovalManager extends Component
{
    public string $patientId;
    public string $carePlanId;

    public array $approvals = [];
    public bool $loading = false;
    public bool $showSmsModal = false;
    public ?string $pendingApprovalId = null;
    public string $smsCode = '';

    public function mount(string $patientId, string $carePlanId)
    {
        $this->patientId = $patientId;
        $this->carePlanId = $carePlanId;
        $this->loadApprovals();
    }

    public function loadApprovals(): void
    {
        try {
            // Find approvals granted to the current employee/entity for this Care Plan
            $response = EHealth::approval()->getApprovals([
                'patient_id' => $this->patientId,
                'status' => 'active'
                // The API can also be queried by resource_id if eHealth specs allow, but patient_id is usually mandatory
            ]);
            
            // Filter by this specific care_plan_id locally if the API returns a broader list
            $allApprovals = $response->getData();
            $this->approvals = array_filter($allApprovals, function ($approval) {
                return $approval['granted_resources'][0]['identifier']['value'] === $this->carePlanId;
            });
            
        } catch (\Exception $e) {
            Log::error('Failed to load Care Plan approvals: ' . $e->getMessage());
            $this->approvals = [];
        }
    }

    public function requestAccess(): void
    {
        $this->loading = true;
        
        try {
            $payload = [
                'granted_resources' => [
                    [
                        'identifier' => [
                            'type' => [
                                'coding' => [
                                    [
                                        'system' => 'eHealth/resources',
                                        'code' => 'care_plan'
                                    ]
                                ]
                            ],
                            'value' => $this->carePlanId
                        ]
                    ]
                ],
                'granted_to' => [
                    'identifier' => [
                        'type' => [
                            'coding' => [
                                [
                                    'system' => 'eHealth/resources',
                                    'code' => 'employee'
                                ]
                            ]
                        ],
                        'value' => auth()->user()?->employee_id // Adjust based on how current employee is accessed in ohealth
                    ]
                ],
                'access_level' => 'write'
            ];

            $response = EHealth::approval()->requestAccess($this->patientId, $payload); // Note: API might require POST to /api/patients/{id}/approvals, adjusting API class
            
            // Assuming response may contain a pending status and an OTP request
            $data = $response->getData();
            Log::info('Approval Request Data: ', ['data' => $data]);
            if (($data['status'] ?? '') === 'new' && isset($data['id'])) {
                $this->pendingApprovalId = $data['id'];
                $this->showSmsModal = true;
                Session::flash('info', __('Підтвердіть запит кодом з SMS.'));
            } else {
                $this->loadApprovals();
                Session::flash('success', __('Доступ успішно запитано.'));
            }

        } catch (\Exception $e) {
            Log::error('Approval request failed: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося запитати доступ. Помилка EHealth.'));
        } finally {
            $this->loading = false;
        }
    }

    public function confirmSms(): void
    {
        // TODO: The actual OTP verification endpoint might be separate or part of a PATCH.
        // Assuming we need to patch the approval with the verification code.
        Session::flash('info', __('SMS verification placeholder'));
        $this->showSmsModal = false;
        $this->loadApprovals();
    }

    public function cancelApproval(string $approvalId): void
    {
        try {
            EHealth::approval()->cancel($approvalId, [
                // May need reason or other metadata based on dictionary
            ]);
            $this->loadApprovals();
            Session::flash('success', __('Доступ скасовано.'));
        } catch (\Exception $e) {
            Log::error('Cancel approval failed: ' . $e->getMessage());
            Session::flash('error', __('Не вдалося скасувати доступ. Помилка EHealth.'));
        }
    }

    public function render()
    {
        return view('livewire.treatment-plan.approvals.manager');
    }
}
