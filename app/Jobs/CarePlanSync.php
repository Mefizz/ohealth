<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\eHealth\EHealth;
use App\Models\TreatmentPlan\TreatmentPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CarePlanSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public TreatmentPlan $treatmentPlan
    ) {
    }

    public function handle(): void
    {
        if (!$this->treatmentPlan->job_id || $this->treatmentPlan->status !== 'PROCESSING') {
            return;
        }

        try {
            $response = EHealth::job()->view($this->treatmentPlan->job_id);
            $jobData = $response->json('data');

            if ($jobData && $jobData['status'] !== 'PROCESSING') {
                $status = $jobData['status'] === 'COMPLETED' ? 'ACTIVE' : 'ERROR';
                
                $validationDetails = null;
                if ($status === 'ERROR' && isset($jobData['response']['error'])) {
                    $validationDetails = $jobData['response']['error'];
                }

                $this->treatmentPlan->update([
                    'status' => $status,
                    'ehealth_id' => $jobData['response']['data']['id'] ?? null, // capture generated CarePlan ID from eHealth
                    'validation_details' => $validationDetails,
                ]);
            } else {
                // If still processing, re-release to the queue to check again later
                $this->release(30);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync CarePlan job status: ' . $e->getMessage(), [
                'treatment_plan_id' => $this->treatmentPlan->id,
                'job_id' => $this->treatmentPlan->job_id,
            ]);
            
            // Re-release to queue on connection error
            $this->release(60);
        }
    }
}
