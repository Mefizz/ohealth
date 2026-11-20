<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\Api\Patient\ServiceRequest as PatientServiceRequestApi;
use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\ServiceRequestErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceRequest extends Request
{
    protected function errorTranslator(): ApiErrorTranslator
    {
        return new ServiceRequestErrorTranslator();
    }

    protected const string ENDPOINT_SERVICE_REQUESTS = '/api/service_requests';
    protected const string ENDPOINT_MEDICAL_EVENTS_SERVICE_REQUESTS = '/api/medical_events/service_requests';

    /**
     * Discover service requests by requisition number.
     */
    public function searchForServiceRequestsByParams(array $params): PromiseInterface|EHealthResponse
    {
        return app(PatientServiceRequestApi::class)->withHeaders($this->options['headers'] ?? [])
            ->searchForServiceRequestsByParams($params);
    }

    /**
     * Qualify a Service Request.
     */
    public function qualify(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateQualification(...));

        return $this->post(self::ENDPOINT_SERVICE_REQUESTS . "/{$id}/actions/qualify", $payload);
    }

    /**
     * Process a Service Request (взяття в роботу / Use Service Request).
     */
    public function process(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => app(PatientServiceRequestApi::class)->validateOperation($response, [202]));

        return $this->patch(self::ENDPOINT_SERVICE_REQUESTS . "/{$id}/actions/use", $payload);
    }

    /** Deprecated by ESOZ; retained as an explicit operation for integrations still using process. */
    public function processLegacy(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => app(PatientServiceRequestApi::class)->validateOperation($response, [202]));

        return $this->patch(self::ENDPOINT_SERVICE_REQUESTS . "/{$id}/actions/process", $payload);
    }

    /**
     * Complete a Service Request (погашення).
     */
    public function complete(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => app(PatientServiceRequestApi::class)->validateOperation($response, id: $id));

        return $this->patch(self::ENDPOINT_SERVICE_REQUESTS . "/{$id}/actions/complete", $payload);
    }

    /**
     * Cancel usage of a Service Request (відміна використання).
     */
    public function cancelUsage(string $id, string $patientId, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => app(PatientServiceRequestApi::class)->validateOperation($response, [202]));

        // PROD Medical Events 9.15.4 documents Release with service_request:use and no request body.
        return $this->patch(self::ENDPOINT_SERVICE_REQUESTS . "/{$id}/actions/release");
    }

    /**
     * Recall a Service Request (відміна за непотрібністю, TV 3.17.1.13).
     */
    public function recall(string $patientId, string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => app(PatientServiceRequestApi::class)->validateOperation($response, [202]));

        return $this->patch("/api/patients/{$patientId}/service_requests/{$id}/actions/recall", $payload);
    }

    protected function validateQualification(EHealthResponse $response): array
    {
        $body = $response->json();
        Validator::make(is_array($body) ? $body : [], [
            'data' => ['present', 'array', 'list'],
            'data.*' => ['required', 'array'],
            'data.*.program_id' => ['required', 'uuid'],
            'data.*.program_name' => ['required', 'string'],
            'data.*.status' => ['required', Rule::in(['VALID', 'INVALID'])],
            'data.*.rejection_reason' => ['sometimes', 'string'],
        ])->validate();

        return $body['data'];
    }
}
