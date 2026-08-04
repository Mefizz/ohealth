<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\Exceptions\ApiException;

/**
 * Legacy static-API shim for MedicationRequest.
 *
 * Delegates every call to the modern instance-based
 * App\Classes\eHealth\Api\Patient\MedicationRequest via EHealth::medicationRequest().
 * This allows MedicationRequestLifecycleService to keep its current static-style call
 * surface while the underlying HTTP transport uses the correct new client.
 */
class MedicationRequest
{
    /**
     * PreQualify Medication Request (API-005-044-0001).
     *
     * @param  array  $payload
     * @return array
     * @throws ApiException
     */
    public static function preQualify(array $payload): array
    {
        return EHealth::medicationRequest()->prequalify($payload)->getData();
    }

    /**
     * Create Medication Request (draft) (API-005-044-0002).
     *
     * @param  array  $payload
     * @return array
     * @throws ApiException
     */
    public static function createMedicationRequest(array $payload): array
    {
        return EHealth::medicationRequest()->createRequest($payload)->getData();
    }

    /**
     * Sign Medication Request (API-005-044-0006).
     *
     * @param  string  $id
     * @param  array  $payload
     * @return array
     * @throws ApiException
     */
    public static function signMedicationRequest(string $id, array $payload): array
    {
        return EHealth::medicationRequest()->signRequest($id, $payload)->getData();
    }

    /**
     * Reject Medication Request (ACTIVE) (API-005-043-0006).
     *
     * @param  string  $id
     * @param  array  $payload
     * @return array
     * @throws ApiException
     */
    public static function rejectMedicationRequest(string $id, array $payload): array
    {
        // Person UUID is required by the new API; resolve from the request payload if present.
        $personUuid = $payload['person_id'] ?? '';

        return EHealth::medicationRequest()->reject($personUuid, $id, $payload)->getData();
    }

    /**
     * Reject un-signed Medication Request (NEW) (API-005-044-0007).
     *
     * @param  string  $id
     * @param  array  $payload
     * @return array
     * @throws ApiException
     */
    public static function rejectUnsignedMedicationRequest(string $id, array $payload): array
    {
        return EHealth::medicationRequest()->rejectRequest($id)->getData();
    }
}
