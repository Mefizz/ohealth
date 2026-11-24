<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\MedicalEvents;

use App\Classes\eHealth\Api\Job;
use App\Classes\eHealth\Api\Patient\PatientApiBase;
use App\Classes\eHealth\Api\Responses\Collections\ServiceRequestPrequalification;
use App\Classes\eHealth\Api\ServiceRequest as ServiceRequestExecutorApi;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\ServiceRequestErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceRequest extends PatientApiBase
{
    protected function errorTranslator(): ApiErrorTranslator
    {
        return new ServiceRequestErrorTranslator();
    }

    /**
     * Create a signed Service Request in eHealth (PKCS#7).
     *
     * @see REST API Create Service Request [API-007-062-0002]
     */
    public function createSigned(string $patientId, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator(Job::validateSubmissionResponse(...));

        return $this->post(self::URL . "/{$patientId}/service_requests", $payload);
    }

    /**
     * Cancel a Service Request (Скасування направлення як entered-in-error).
     */
    public function cancel(string $patientId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator(Job::validateSubmissionResponse(...));

        return $this->patch(self::URL . "/{$patientId}/service_requests/{$id}/actions/cancel", $payload);
    }

    /**
     * Recall a Service Request (відміна за непотрібністю, TV 3.17.1.13).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recall(string $patientId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator(Job::validateSubmissionResponse(...));

        return $this->patch(self::URL . "/{$patientId}/service_requests/{$id}/actions/recall", $payload);
    }

    /**
     * Search for Service Requests by parameters.
     */
    public function searchForServiceRequestsByParams(array $params): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateMany(...));

        return $this->get('/api/service_requests', $params);
    }

    /**
     * Get Service Requests by search parameters in patient context.
     *
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getBySearchParams(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setDefaultPageSize();
        $this->setValidator($this->validateMany(...));

        $query = array_merge($this->options['query'], $query);

        return $this->get(self::URL . "/{$patientId}/service_requests", $query);
    }

    /**
     * Get a specific Service Request by ID.
     */
    public function getById(string $patientId, string $id, array $query = [], bool $preserveApiKeys = false): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateDetails($response, $preserveApiKeys));

        return $this->get(self::URL . "/{$patientId}/service_requests/{$id}", $query);
    }

    /**
     * Pre-qualify service request data before creation.
     *
     * @see REST API PreQualify Service Request [API-007-062-0001]
     */
    public function prequalify(string $patientId, array $payload): PromiseInterface|EHealthResponse
    {
        $rules = [
            'service_request.code.identifier.value' => ['required'],
            'service_request.requester_employee.identifier.value' => ['required', 'uuid'],
            'service_request.requester_legal_entity.identifier.value' => ['required', 'uuid'],
            'programs' => ['bail', 'present', Rule::requiredIf(($payload['programs'] ?? []) !== []), 'array', 'list'],
            'programs.*.identifier' => ['required', 'array'],
            'programs.*.identifier.value' => ['required', 'uuid', 'distinct:ignore_case'],
        ];
        $validated = Validator::make($payload, $rules)->validate();

        $programIds = array_map('strtolower', data_get($validated, 'programs.*.identifier.value', []));
        $this->setValidator(fn (EHealthResponse $response): ServiceRequestPrequalification => $this->validatePrequalify($response, $programIds));

        return $this->post(self::URL . "/{$patientId}/service_requests/prequalify", $payload);
    }

    /**
     * Resend SMS with OTP for an active service request.
     *
     * @see REST API Resend SMS on Service Request [API-007-062-0009]
     */
    public function resendSms(string $patientId, string $id): PromiseInterface|EHealthResponse
    {
        return $this->post(self::URL . "/{$patientId}/service_requests/{$id}/actions/resend", []);
    }

    /**
     * Executor actions live on the facility-scoped Service Request API.
     * EHealth::serviceRequest() uses this client for patient-scoped create/search,
     * so these methods delegate to Api\ServiceRequest for use / complete / qualify.
     */
    public function qualify(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        return $this->executorApi()->qualify($id, $payload);
    }

    public function process(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        return $this->executorApi()->process($id, $payload);
    }

    public function complete(string $id, array $payload = []): PromiseInterface|EHealthResponse
    {
        return $this->executorApi()->complete($id, $payload);
    }

    public function cancelUsage(string $id, string $patientId, array $payload = []): PromiseInterface|EHealthResponse
    {
        return $this->executorApi()->cancelUsage($id, $patientId, $payload);
    }

    private function executorApi(): ServiceRequestExecutorApi
    {
        return app(ServiceRequestExecutorApi::class);
    }

    protected function validateDetails(EHealthResponse $response, bool $preserveApiKeys = false): array
    {
        $native = $response->getData();
        $data = $this->replaceEHealthPropNames($native);
        $toValidate = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

        $validator = Validator::make($toValidate, [
            'uuid' => 'required|string',
            'status' => 'required|string',
            'requisition' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'ServiceRequest details validation failed: ' . implode(', ', $validator->errors()->all())
            );
            throw new ValidationException($validator);
        }

        // Signed snapshots retain the API's keys; both forms pass the same validator.
        return $preserveApiKeys ? $native : $data;
    }

    protected function validateMany(EHealthResponse $response): array
    {
        $transformedData = [];
        $items = $response->getData();
        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }
        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $transformedData[] = $this->replaceEHealthPropNames($item);
                }
            }
        }

        $validator = Validator::make($transformedData, [
            '*' => 'array',
            '*.uuid' => 'required|string',
            '*.status' => 'required|string',
            '*.requisition' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'ServiceRequest many validation failed: ' . implode(', ', $validator->errors()->all())
            );
            throw new ValidationException($validator);
        }

        return $response->getData();
    }

    protected function validatePrequalify(EHealthResponse $response, array $programIds): ServiceRequestPrequalification
    {
        $body = (array) $response->json();
        $body['http_status'] = $response->status();
        // Laravel decodes both {} and [] as an empty array. Only a JSON list can contain verdicts.
        $verdicts = $response->object()->data ?? null;
        if (is_object($verdicts)) {
            $body['data'] = $verdicts;
        }

        $rules = [
            'http_status' => ['required', Rule::in([200])],
            'error' => ['missing'],
            'data' => ['bail', Rule::requiredIf($programIds !== [] || ($body['data'] ?? []) !== []), 'array', 'list', 'size:'.count($programIds)],
            'data.*' => ['array'],
            'data.*.program_id' => [
                'bail', 'required', 'uuid', 'distinct:ignore_case',
                function (string $attribute, string $value, Closure $fail) use ($programIds): void {
                    if (!in_array(strtolower($value), $programIds, true)) {
                        $fail(__('care-plan.referral_prequalify_failed'));
                    }
                },
            ],
            'data.*.program_name' => ['present', 'string'],
            'data.*.status' => ['required', Rule::in(['VALID', 'INVALID'])],
            'data.*.rejection_reason' => ['sometimes', 'string'],
            'urgent.authentication_method_current.type' => ['required', Rule::in(['OTP', 'OFFLINE', 'THIRD_PERSON'])],
            'urgent.authentication_method_current.number' => ['sometimes', 'string'],
        ];
        $validated = Validator::make($body, $rules)->validate();

        return new ServiceRequestPrequalification($validated['data'] ?? []);
    }
}
