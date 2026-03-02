<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Request;

class CarePlan extends EHealthRequest
{
    public const string URL = '/api/care_plans';

    /**
     * Creates a Care Plan in E-Health using a signed request.
     *
     * @param string $patientId The UUID of the patient in E-Health
     * @param array $payload The payload containing 'signed_data'
     * @return EHealthResponse
     */
    public function create(string $patientId, array $payload): EHealthResponse
    {
        // Care Plan creation in eHealth often goes to a specific endpoint
        // e.g. /api/patients/{patientId}/care_plans or /api/care_plans
        // Following standard convention for EHealth here
        $url = self::URL;

        return (new Request('POST', $url, $payload))->sendRequest();
    }
}
