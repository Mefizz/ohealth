<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Patient;

use App\Classes\eHealth\Api\Patient\PatientApiBase;
use App\Classes\eHealth\Api\ServiceRequest as ServiceRequestExecutorApi;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\ServiceRequestErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use GuzzleHttp\Promise\PromiseInterface;
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
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateOperation($response, [202]));

        return $this->post(self::URL . "/{$patientId}/service_requests", $payload);
    }

    /**
     * Cancel a Service Request (Скасування направлення як entered-in-error).
     */
    public function cancel(string $patientId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateOperation($response, requireStatusReason: true));

        return $this->patch(self::URL . "/{$patientId}/service_requests/{$id}/actions/cancel", $payload);
    }

    /**
     * Recall a Service Request (відміна за непотрібністю, TV 3.17.1.13).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recall(string $patientId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateOperation($response, [202]));

        return $this->patch(self::URL . "/{$patientId}/service_requests/{$id}/actions/recall", $payload);
    }

    /**
     * Search for Service Requests by parameters.
     */
    public function searchForServiceRequestsByParams(array $params): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateMany($response, withIdentity: true));

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

    public function getByEpisode(string $patientId, string $episodeId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateMany(...));

        return $this->get(self::URL . "/{$patientId}/episodes/{$episodeId}/service_requests", $query);
    }

    public function getByEpisodeId(string $patientId, string $episodeId, string $id): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateDetails($response, preserveApiKeys: true));

        return $this->get(self::URL . "/{$patientId}/episodes/{$episodeId}/service_requests/{$id}");
    }

    public function getByComposition(string $patientId, string $compositionId, string $id): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateDetails($response, preserveApiKeys: true));

        return $this->get(self::URL . "/{$patientId}/compositions/{$compositionId}/service_requests/{$id}");
    }

    public function getBySpecimen(string $specimenId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setDefaultPageSize();
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateMany($response, withIdentity: true));

        return $this->get("/api/specimens/{$specimenId}/service_requests", array_merge($this->options['query'], $query));
    }

    /**
     * Pre-qualify service request data before creation.
     *
     * @see REST API PreQualify Service Request [API-007-062-0001]
     */
    public function prequalify(string $patientId, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validatePrequalify(...));

        return $this->post(self::URL . "/{$patientId}/service_requests/prequalify", $payload);
    }

    /**
     * Resend SMS with OTP for an active service request.
     *
     * @see REST API Resend SMS on Service Request [API-007-062-0009]
     */
    public function resendSms(string $patientId, string $id): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateResend(...));

        return $this->patch(self::URL . "/{$patientId}/service_requests/{$id}/actions/resend", []);
    }

    /**
     * Executor actions live on the facility-scoped Service Request API.
     * EHealth::serviceRequest() is bound to this Patient client for create/search,
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
        return app(ServiceRequestExecutorApi::class)->withHeaders($this->options['headers'] ?? []);
    }

    protected function validateResend(EHealthResponse $response): array
    {
        // The documented 200 response has no body. A 202 receipt is accepted, never proof of SMS delivery.
        if ($response->status() === 200 && trim($response->body()) === '') {
            return ['status' => 'processed'];
        }
        if ($response->status() === 201) {
            return $this->validateDetails($response, preserveApiKeys: true);
        }

        return $this->validateOperation($response);
    }

    /** A write either returns a complete synchronous resource or the documented pending receipt. */
    public function validateOperation(EHealthResponse $response, array $allowedStatuses = [201, 202], bool $requireStatusReason = false): array
    {
        if (!in_array($response->status(), $allowedStatuses, true)) {
            throw ValidationException::withMessages(['response' => __('referrals.messages.operation_failed')]);
        }
        if ($response->status() === 201) {
            $resource = $this->validateDetails($response, preserveApiKeys: true);
            if ($requireStatusReason) {
                Validator::make($resource, ['status_reason' => ['required', 'array']])->validate();
            }

            return $resource;
        }
        $body = $response->json();
        Validator::make(is_array($body) ? $body : [], [
            'data' => ['required', 'array'],
            'data.status' => ['required', Rule::in(['pending'])],
            'data.eta' => ['sometimes', 'string'],
            'data.links' => ['sometimes', 'array', 'list'],
            'data.links.*.entity' => ['required', 'string'],
            'data.links.*.href' => ['required', 'string'],
        ])->validate();
        if ($response->status() !== 202) {
            throw ValidationException::withMessages(['response' => __('referrals.messages.operation_failed')]);
        }

        return $body['data'];
    }

    /** Reuse the API contract for embedded job resources before any persistence or completion decision. */
    public function validateResource(array $resource): array
    {
        Validator::make($resource, $this->resourceRules('', details: true))->validate();

        return $resource;
    }

    public function validateJobResult(array $result): array
    {
        Validator::make($result, [
            'status' => ['required', Rule::in(['pending', 'processing', 'accepted', 'queued', 'processed', 'failed'])],
            'response_data' => ['sometimes', 'array'],
            'result' => ['sometimes', 'array'],
            'data' => ['sometimes', 'array'],
        ])->validate();
        if ($result['status'] !== 'processed') {
            return $result;
        }
        $resource = $result['response_data'] ?? ($result['result'] ?? ($result['data'] ?? []));
        // Job envelopes may contain a single resource, a singleton list, or one additional data wrapper.
        $resource = $resource[0] ?? $resource;
        $resource = $resource['response_data'] ?? ($resource['result'] ?? ($resource['data'] ?? $resource));
        $resource = $resource[0] ?? $resource;
        if ($resource !== []) {
            $result['response_data'] = $this->validateResource($resource);
        }

        return $result;
    }

    /** Validate native FHIR before renaming any identifier or handing a snapshot to signing/persistence. */
    protected function validateDetails(EHealthResponse $response, bool $preserveApiKeys = false): array
    {
        $body = $response->json();
        Validator::make(is_array($body) ? $body : [], [
            'data' => ['required', 'array'],
            ...$this->resourceRules('data.', details: true),
        ])->validate();
        $native = $body['data'];

        return $preserveApiKeys ? $native : $this->replaceEHealthPropNames($native);
    }

    /** A malformed item must reject the page, never disappear into a seemingly successful empty registry. */
    protected function validateMany(EHealthResponse $response, bool $withIdentity = false): array
    {
        $body = $response->json();
        Validator::make(is_array($body) ? $body : [], [
            'data' => ['present', 'array', 'list'],
            'data.*' => ['required', 'array'],
            'paging' => ['sometimes', 'array'],
            'paging.page' => ['required_with:paging', 'integer', 'min:1'],
            'paging.page_size' => ['required_with:paging', 'integer', 'between:1,100'],
            'paging.total_entries' => ['required_with:paging', 'integer', 'min:0'],
            'paging.total_pages' => ['required_with:paging', 'integer', 'min:0'],
            ...$this->resourceRules('data.*.'),
            ...($withIdentity ? $this->identityRules('data.*.identity') : []),
        ])->validate();

        return $body['data'];
    }

    protected function validatePrequalify(EHealthResponse $response): array
    {
        $body = $response->json();
        Validator::make(is_array($body) ? $body : [], [
            'data' => ['present', 'array', 'list'],
            'data.*' => ['required', 'array'],
            'data.*.program_id' => ['required', 'uuid'],
            'data.*.program_name' => ['required', 'string'],
            'data.*.status' => ['required', Rule::in(['VALID', 'INVALID'])],
            'data.*.rejection_reason' => ['sometimes', 'string'],
            'urgent.authentication_method_current.type' => ['required', Rule::in(['OTP', 'OFFLINE', 'THIRD_PERSON'])],
            'urgent.authentication_method_current.number' => ['sometimes', 'string'],
        ])->validate();

        return $body['data'];
    }

    protected function identityRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array'],
            $prefix.'.last_name' => ['present', 'nullable', 'string'],
            $prefix.'.first_name' => ['present', 'nullable', 'string'],
            $prefix.'.second_name' => ['present', 'nullable', 'string'],
            $prefix.'.gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            $prefix.'.age' => ['present', 'numeric'],
        ];
    }

    /** Medical Events 9.15.6: patient details and registry share the core resource contract. */
    protected function resourceRules(string $prefix, bool $details = false): array
    {
        $rules = [
            'id' => ['required', 'uuid'],
            'status' => ['required', 'string'],
            'requisition' => ['present', 'string'],
            'intent' => ['required', 'string'],
            'status_history' => ['present', 'array', 'list'],
            'category' => ['required', 'array'],
            'category.coding' => ['required', 'array', 'list'],
            'category.coding.*.system' => ['required', 'string'],
            'category.coding.*.code' => ['required', 'string'],
            'reason_reference' => ['present', 'array', 'list'],
            'note' => ['present', 'string'],
            'patient_instruction' => ['present', 'string'],
            'expiration_date' => ['present', 'string'],
            'occurrence_period' => ['sometimes', 'prohibited_with:'.$prefix.'occurrence_date_time', 'array'],
            'occurrence_period.start' => ['required_with:'.$prefix.'occurrence_period', 'date'],
            'occurrence_period.end' => ['sometimes', 'date'],
            'occurrence_date_time' => ['sometimes', 'date'],
        ];
        foreach (['code', 'subject', 'requester_employee', 'requester_legal_entity', 'used_by_employee', 'used_by_legal_entity'] as $field) {
            $rules[$field] = ['present', 'array', 'required_array_keys:display_value'];
            $rules[$field.'.display_value'] = ['present', 'string'];
        }
        foreach (['code', 'subject', 'requester_employee', 'requester_legal_entity', 'used_by_employee', 'used_by_legal_entity', 'program', 'program_service', 'context', 'completed_with', 'performer', 'location_reference'] as $field) {
            if (!isset($rules[$field])) {
                $rules[$field] = ['sometimes', 'array', 'required_array_keys:display_value'];
            }
            if (in_array($field, ['program_service', 'completed_with', 'performer', 'location_reference'], true)) {
                $rules[$field][] = 'required_array_keys:identifier';
            }
            $rules[$field.'.identifier'] = ['sometimes', 'array', 'required_array_keys:type,value'];
            $rules[$field.'.display_value'] = ['present_with:'.$prefix.$field, 'string'];
            $rules[$field.'.identifier.value'] = ['required_with:'.$prefix.$field.'.identifier', 'string'];
            $rules[$field.'.identifier.type.coding'] = ['required_with:'.$prefix.$field.'.identifier', 'array', 'list'];
            $rules[$field.'.identifier.type.coding.*.system'] = ['required', 'string'];
            $rules[$field.'.identifier.type.coding.*.code'] = ['required', 'string'];
        }
        foreach (['based_on', 'supporting_info', 'reason_reference', 'permitted_resources', 'specimens', 'focus'] as $field) {
            $rules[$field] ??= ['sometimes', 'array', 'list'];
            $rules[$field.'.*'] = ['array'];
            $rules[$field.'.*.identifier'] = ['sometimes', 'array', in_array($field, ['reason_reference', 'permitted_resources'], true) ? 'required_array_keys:value' : 'required_array_keys:type,value'];
            $rules[$field.'.*.identifier.value'] = ['required_with:'.$prefix.$field.'.*.identifier', 'string'];
            $rules[$field.'.*.identifier.type'] = [in_array($field, ['reason_reference', 'permitted_resources'], true)
                ? 'sometimes' : 'required_with:'.$prefix.$field.'.*.identifier', 'array'];
            $rules[$field.'.*.identifier.type.coding'] = ['required_with:'.$prefix.$field.'.*.identifier.type', 'array', 'list'];
            $rules[$field.'.*.identifier.type.coding.*.system'] = ['required', 'string'];
            $rules[$field.'.*.identifier.type.coding.*.code'] = [in_array($field, ['reason_reference', 'permitted_resources'], true) ? 'sometimes' : 'required', 'string'];
            if ($field !== 'based_on') {
                $rules[$field.'.*.display_value'] = ['present', 'string'];
            }
        }
        foreach (['status_history' => 'status', 'program_processing_status_history' => 'program_processing_status'] as $field => $statusField) {
            $rules[$field] ??= ['sometimes', 'array', 'list'];
            $rules[$field.'.*'] = ['array'];
            $rules[$field.'.*.'.$statusField] = ['required', 'string'];
            $rules[$field.'.*.inserted_at'] = ['required', 'string'];
            if ($field !== 'status_history') {
                $rules[$field.'.*.inserted_by'] = ['required', 'string'];
            }
        }
        foreach (['used_by_employee_history' => 'used_by_employee', 'used_by_legal_entity_history' => 'used_by_legal_entity'] as $field => $referenceField) {
            $rules[$field] = ['sometimes', 'array', 'list'];
            $rules[$field.'.*.inserted_at'] = ['required', 'string'];
            $rules[$field.'.*.inserted_by'] = ['required', 'string'];
            $rules[$field.'.*.'.$referenceField] = ['present', 'array', 'required_array_keys:display_value'];
            $rules[$field.'.*.'.$referenceField.'.display_value'] = ['present', 'string'];
            $rules[$field.'.*.'.$referenceField.'.identifier'] = ['sometimes', 'array'];
            $rules[$field.'.*.'.$referenceField.'.identifier.value'] = ['required_with:'.$prefix.$field.'.*.'.$referenceField.'.identifier', 'string'];
            $rules[$field.'.*.'.$referenceField.'.identifier.type.coding'] = ['required_with:'.$prefix.$field.'.*.'.$referenceField.'.identifier', 'array', 'list'];
            $rules[$field.'.*.'.$referenceField.'.identifier.type.coding.*.system'] = ['required', 'string'];
            $rules[$field.'.*.'.$referenceField.'.identifier.type.coding.*.code'] = ['required', 'string'];
        }
        foreach (['performer_type', 'status_reason', 'status_history.*.status_reason'] as $field) {
            $rules[$field] = ['sometimes', 'array'];
            $rules[$field][] = 'required_array_keys:coding';
            $rules[$field.'.coding'] = ['required_with:'.$prefix.$field, 'array', 'list'];
            $rules[$field.'.coding.*.system'] = ['required', 'string'];
            $rules[$field.'.coding.*.code'] = ['required', 'string'];
            $rules[$field.'.text'] = ['sometimes', 'string'];
        }
        $rules['inform_with'] = ['sometimes', 'array'];
        $rules['inform_with.auth_method_id'] = ['sometimes', 'uuid'];
        foreach (['priority', 'program_processing_status', 'explanatory_letter', 'inserted_at', 'updated_at'] as $field) {
            $rules[$field] = ['sometimes', 'string'];
        }
        foreach (['quantity', 'remaining_quantity'] as $field) {
            $rules[$field] = [$details ? 'required' : 'sometimes', 'array'];
            $rules[$field.'.value'] = ['required_with:'.$prefix.$field, 'numeric'];
            $rules[$field.'.system'] = ['required_with:'.$prefix.$field, 'string'];
            $rules[$field.'.code'] = ['required_with:'.$prefix.$field, 'string'];
            $rules[$field.'.unit'] = ['sometimes', 'string'];
            $rules[$field.'.comparator'] = ['sometimes', 'string'];
        }

        $prefixed = [];
        foreach ($rules as $field => $constraints) {
            $prefixed[$prefix.$field] = $constraints;
        }

        return $prefixed;
    }
}
