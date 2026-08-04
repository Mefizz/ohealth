<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\Api\CarePlan;
use App\Classes\eHealth\Api\CarePlanActivity;
use Exception;
use Illuminate\Support\Facades\Log;

class CarePlanLifecycleService
{
    /**
     * Prepares Care Plan for Cancellation by fetching the object and appending status_reason.
     * The result should be passed to the frontend for Digital Signature (KEП).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  array  $statusReason  (e.g., ['coding' => [['system' => 'eHealth...', 'code' => '...']]])
     * @return array
     */
    public function prepareForCancelCarePlan(string $personId, string $carePlanId, array $statusReason): array
    {
        try {
            $api = new CarePlan();
            $response = $api->getDetails($personId, $carePlanId);
            $carePlan = $response->getData(); // Note: activities should not be present or should be unset

            $carePlan['status_reason'] = $statusReason;

            // To be signed
            return $carePlan;
        } catch (Exception $e) {
            Log::error('Care Plan Prepare Cancel failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute Care Plan Cancel (with signed content).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  array  $signedPayload  (must contain signed_content, signature, signed_content_encoding)
     * @return array
     */
    public function cancelCarePlan(string $personId, string $carePlanId, array $signedPayload): array
    {
        try {
            $api = new CarePlan();
            $response = $api->cancel($personId, $carePlanId, $signedPayload);

            return $response->getData();
        } catch (Exception $e) {
            Log::error('Care Plan Cancel failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute Care Plan Complete (No signature required).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  array  $statusReason
     * @return array
     */
    public function completeCarePlan(string $personId, string $carePlanId, array $statusReason): array
    {
        try {
            $api = new CarePlan();
            $payload = ['status_reason' => $statusReason];
            $response = $api->complete($personId, $carePlanId, $payload);

            return $response->getData();
        } catch (Exception $e) {
            Log::error('Care Plan Complete failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Prepares Care Plan Activity for Cancellation by fetching the object and appending status_reason.
     * The result should be passed to the frontend for Digital Signature (KEП).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  string  $activityId
     * @param  array  $statusReason
     * @return array
     */
    public function prepareForCancelActivity(string $personId, string $carePlanId, string $activityId, array $statusReason): array
    {
        try {
            $api = new CarePlanActivity();
            $response = $api->getDetails($personId, $carePlanId, $activityId);
            $activity = $response->getData();

            if (isset($activity['detail'])) {
                $activity['detail']['status_reason'] = $statusReason;
            }

            return $activity;
        } catch (Exception $e) {
            Log::error('Care Plan Activity Prepare Cancel failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute Care Plan Activity Cancel (with signed content).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  string  $activityId
     * @param  array  $signedPayload
     * @return array
     */
    public function cancelActivity(string $personId, string $carePlanId, string $activityId, array $signedPayload): array
    {
        try {
            $api = new CarePlanActivity();
            $response = $api->cancel($personId, $carePlanId, $activityId, $signedPayload);

            return $response->getData();
        } catch (Exception $e) {
            Log::error('Care Plan Activity Cancel failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute Care Plan Activity Complete (No signature required).
     *
     * @param  string  $personId
     * @param  string  $carePlanId
     * @param  string  $activityId
     * @param  array  $outcomeCodeableConcept
     * @return array
     */
    public function completeActivity(string $personId, string $carePlanId, string $activityId, array $outcomeCodeableConcept): array
    {
        try {
            $api = new CarePlanActivity();
            $payload = ['outcome_codeable_concept' => $outcomeCodeableConcept];
            $response = $api->complete($personId, $carePlanId, $activityId, $payload);

            return $response->getData();
        } catch (Exception $e) {
            Log::error('Care Plan Activity Complete failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
