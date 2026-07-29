<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\Api\MedicationRequest;
use Exception;
use Illuminate\Support\Facades\Log;

class MedicationRequestLifecycleService
{
    /**
     * PreQualify Medication Request.
     *
     * @param  array  $payload
     * @return array
     */
    public function preQualify(array $payload): array
    {
        try {
            $response = MedicationRequest::preQualify($payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Prequalify failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create Medication Request (Draft).
     *
     * @param  array  $payload
     * @return array
     */
    public function createDraft(array $payload): array
    {
        try {
            $response = MedicationRequest::createMedicationRequest($payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Create Draft failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sign Medication Request.
     *
     * @param  string  $id
     * @param  array  $payload  (contains signed_content)
     * @return array
     */
    public function sign(string $id, array $payload): array
    {
        try {
            $response = MedicationRequest::signMedicationRequest($id, $payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Sign failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject Medication Request (ACTIVE).
     *
     * @param  string  $id
     * @param  array  $payload
     * @return array
     */
    public function reject(string $id, array $payload): array
    {
        try {
            $response = MedicationRequest::rejectMedicationRequest($id, $payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Reject failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject un-signed Medication Request (NEW).
     *
     * @param  string  $id
     * @param  array  $payload
     * @return array
     */
    public function rejectDraft(string $id, array $payload): array
    {
        try {
            $response = MedicationRequest::rejectUnsignedMedicationRequest($id, $payload);

            return $response['data'] ?? $response;
        } catch (Exception $e) {
            Log::error('ePrescription Reject Draft failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
