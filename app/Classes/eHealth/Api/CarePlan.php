<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanGetMany;
use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\MedicalEventsErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use App\Core\Arr;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CarePlan extends Request
{
    protected const string URL = '/api/care_plans';

    /**
     * Create a Care Plan. Validation returns CarePlanDetails for 201 or a pending Collection receipt for 202.
     *
     * @param  string  $patientId
     * @param  array  $payload
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function create(string $patientId, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateSubmission(...));

        return $this->post("/api/patients/{$patientId}/care_plans", $payload);
    }

    /**
     * Get short Care Plans by requisition; validate CarePlanGetMany before adapting it to legacy array callers.
     *
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getMany(array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateRequisitionForLegacy(...));

        return $this->get(self::URL, $query);
    }

    /**
     * Get a CarePlan by patient and plan UUID. Adapt validated CarePlanDetails to the existing array or signing snapshot contract.
     *
     * @param  string  $patientId
     * @param  string  $id
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getDetails(string $patientId, string $id, array $query = [], bool $preserveApiKeys = false): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $preserveApiKeys
            ? $this->validateDetails($response, $id, $patientId)->native
            : Arr::toCamelCase($this->validateDetails($response, $id, $patientId)->native));

        return $this->get("/api/patients/{$patientId}/care_plans/{$id}", $query);
    }

    /**
     * Cancel a Care Plan.
     * Note: This requires a Digital Signature (DS).
     * The payload must contain 'signed_data' which is the Care Plan object (without activities) + status_reason, signed by KEП.
     *
     * @param  string  $personId
     * @param  string  $id
     * @param  array  $payload
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function cancel(string $personId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateSubmission(...));

        return $this->patch("/api/patients/$personId/care_plans/$id/actions/cancel", $payload);
    }

    /**
     * Complete a Care Plan.
     * Note: This does NOT require a Digital Signature (DS).
     * The payload just needs to contain 'status_reason'.
     *
     * @param  string  $personId
     * @param  string  $id
     * @param  array  $payload
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function complete(string $personId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateSubmission(...));

        return $this->patch("/api/patients/$personId/care_plans/$id/actions/complete", $payload);
    }

    /**
     * Get the patient's short Care Plans; validate CarePlanGetMany before adapting it to legacy array callers.
     *
     * @param  string  $patientId
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     */
    public function getBySearchParams(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateManyForLegacy(...));

        return $this->get("/api/patients/$patientId/care_plans", $query);
    }

    /**
     * Get a list of summary info about care plans.
     *
     * @param  string  $patientId
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/patient-summary/get-care-plans
     */
    public function getSummary(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateManyForLegacy(...));
        $this->setDefaultPageSize();

        $mergedQuery = array_merge($this->options['query'], $query);

        return $this->get("/api/patients/$patientId/summary/care_plans", $mergedQuery);
    }

    /** Get full care plan details in an authorized Composition context. */
    public function getInCompositionContext(string $patientId, string $compositionId, string $id): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateResponse(...));

        return $this->get("/api/patients/$patientId/compositions/$compositionId/care_plans/$id");
    }

    /** Get the V2 short list, including category and diagnosis concepts. */
    public function getManyV2(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateManyV2(...));

        return $this->get("/api/v2/patients/$patientId/care_plans", $query);
    }

    protected function errorTranslator(): ApiErrorTranslator
    {
        return new MedicalEventsErrorTranslator();
    }

    /** Validate full details before wrapping nested concepts for DTO mapping. */
    protected function validateResponse(EHealthResponse $response): CarePlanDetails
    {
        return $this->validateResource($response->getData());
    }

    /** GET details additionally returns the patient's current informing method in urgent. */
    protected function validateDetails(EHealthResponse $response, string $id, string $patientId): CarePlanDetails
    {
        Validator::make($response->json(), [
            'urgent' => 'required|array',
            'urgent.informing_method_current' => 'required|array',
            'urgent.informing_method_current.type' => 'required|in:OTP,OFFLINE,THIRD_PERSON',
            'urgent.informing_method_current.phone_number' => 'sometimes|string',
        ])->validate();
        Validator::make(['status_code' => $response->getStatusCode()], ['status_code' => 'in:200'])->validate();

        return $this->validateResource($response->getData(), $id, $patientId);
    }

    /** Validate an embedded job resource using the same contract as GET details and synchronous creation. */
    public function validateResource(array $data, ?string $id = null, ?string $patientId = null): CarePlanDetails
    {
        $rules = $this->validationRules();
        if ($id !== null) {
            $rules['id'] .= '|in:'.$id;
        }
        if ($patientId !== null) {
            $rules['subject.identifier.value'] .= '|in:'.$patientId;
        }
        Validator::make($data, $rules)->validate();

        return new CarePlanDetails($data);
    }

    /** Get full details as the typed input to the CarePlan Model DTO. */
    public function getById(string $patientId, string $id): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): CarePlanDetails => $this->validateDetails($response, $id, $patientId));

        return $this->get("/api/patients/$patientId/care_plans/$id");
    }

    /** Array compatibility adapters for the existing registry and summary consumers. */
    protected function validateManyForLegacy(EHealthResponse $response): array
    {
        return Arr::toCamelCase($this->validateMany($response)->toArray());
    }

    protected function validateRequisitionForLegacy(EHealthResponse $response): array
    {
        return Arr::toCamelCase($this->validateRequisition($response)->toArray());
    }

    /** A 201 contains full details; a 202 contains a pending job receipt. */
    protected function validateSubmission(EHealthResponse $response): CarePlanDetails|Collection
    {
        if ($response->getStatusCode() === 201) {
            return $this->validateResponse($response);
        }
        Validator::make(['status_code' => $response->getStatusCode()], ['status_code' => 'in:202'])->validate();

        $data = $response->getData();
        Validator::make($data, [
            'status' => 'required|string',
            'eta' => 'sometimes|string',
            'links' => 'sometimes|array',
            'links.*' => 'required|array',
            'links.*.href' => 'required|string',
            'links.*.entity' => 'required|string',
        ])->validate();

        return new Collection($data);
    }

    /** Patient list and patient summary share the documented short shape. */
    protected function validateMany(EHealthResponse $response): CarePlanGetMany
    {
        return $this->validateList($response, $this->validationRules(details: false));
    }

    /** Requisition search additionally exposes the patient's short identity. */
    protected function validateRequisition(EHealthResponse $response): CarePlanGetMany
    {
        return $this->validateList($response, $this->validationRules(details: false) + [
            'identity' => 'required|array',
            'identity.last_name' => 'required|string',
            'identity.first_name' => 'required|string',
            'identity.second_name' => 'present|string',
            'identity.gender' => 'required|in:MALE,FEMALE',
            'identity.age' => 'required|numeric',
        ]);
    }

    protected function validateManyV2(EHealthResponse $response): CarePlanGetMany
    {
        return $this->validateList($response, array_replace($this->validationRules(details: false), [
            'category' => 'required|array',
            'addresses' => 'present|array',
        ]));
    }

    protected function validateList(EHealthResponse $response, array $itemRules): CarePlanGetMany
    {
        Validator::make(['status_code' => $response->getStatusCode()], ['status_code' => 'in:200'])->validate();
        $data = $response->getData();
        $rules = collect($itemRules)->mapWithKeys(
            static fn (string $rule, string $key): array => ["data.*.$key" => str_replace('required_with:', 'required_with:data.*.', $rule)]
        )->all();
        $validator = Validator::make($response->json(), [
            'data' => 'present|array|list',
            'data.*' => 'required|array',
            'paging' => 'sometimes|array',
            'paging.page' => 'required_with:paging|integer|min:1',
            'paging.page_size' => 'sometimes|integer|min:1',
            'paging.total_pages' => 'sometimes|integer|min:0',
            'paging.total_entries' => 'sometimes|integer|min:0',
            ...$rules,
        ]);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error('CarePlan list validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        $validator->validate();

        return new CarePlanGetMany($data);
    }

    /** Medical Events 9.15.6, Care Plan details and short list response schemas. */
    protected function validationRules(bool $details = true): array
    {
        $rules = [
            'id' => 'required|uuid',
            'status' => 'required|in:new,active,completed,cancelled,cencelled,terminated',
            'requisition' => 'required|string',
            'intent' => 'required|in:order',
            'period' => 'required|array',
            'period.start' => 'required|string|date',
            'period.end' => 'sometimes|string|date',
            'title' => $details ? 'required|string' : 'sometimes|string',
            'description' => 'sometimes|string',
            'note' => 'sometimes|string',
            'context' => 'sometimes|nullable|string',
            'inform_with' => 'sometimes|array',
            'inform_with.auth_method_id' => 'sometimes|uuid',
            'addresses' => $details ? 'present|array' : 'sometimes|array',
            'addresses.*' => 'required|array',
            'supporting_info' => 'sometimes|array',
            'supporting_info.*' => 'required|array',
            'contributor' => 'sometimes|array',
            'contributor.*' => 'required|array',
            'status_history' => $details ? 'present|array' : 'sometimes|array',
            'status_history.*' => 'required|array',
            'status_history.*.status' => 'required|string',
            'status_history.*.inserted_at' => 'required|string|date',
            'status_history.*.inserted_by' => 'required|uuid',
            'managing_organization.display_value' => 'required|string',
        ];

        foreach (['category', 'terms_of_service', 'status_reason', 'addresses.*', 'status_history.*.status_reason'] as $field) {
            $rules[$field] ??= ($field === 'terms_of_service' || ($details && $field === 'category')) ? 'required|array' : 'sometimes|array';
            $rules[$field . '.coding'] = 'required_with:' . $field . '|array';
            $rules[$field . '.coding.*'] = 'required|array';
            $rules[$field . '.coding.*.system'] = 'required|string';
            $rules[$field . '.coding.*.code'] = 'required|string';
            $rules[$field . '.text'] = 'sometimes|string';
        }
        $rules['terms_of_service.coding.*.code'] = 'required|in:INPATIENT,OUTPATIENT';

        foreach (['subject', 'encounter', 'author', 'managing_organization', 'based_on', 'part_of', 'care_manager', 'supporting_info.*', 'contributor.*'] as $field) {
            $rules[$field] ??= in_array($field, ['subject', 'encounter', 'author', 'managing_organization'], true) ? 'required|array' : 'sometimes|array';
            $rules[$field . '.identifier'] = 'required_with:' . $field . '|array';
            $rules[$field . '.identifier.value'] = 'required_with:' . $field . '.identifier|uuid';
            $rules[$field . '.identifier.type'] = 'required_with:' . $field . '.identifier|array';
            $rules[$field . '.identifier.type.coding'] = 'sometimes|array';
            $rules[$field . '.identifier.type.coding.*'] = 'required|array';
            $rules[$field . '.identifier.type.coding.*.system'] = 'required|string';
            $rules[$field . '.identifier.type.coding.*.code'] = 'required|string';
            $rules[$field . '.identifier.type.text'] = 'sometimes|string';
            $rules[$field . '.display_value'] ??= 'sometimes|string';
        }

        foreach (['inserted_at', 'updated_at'] as $field) {
            $rules[$field] = $details ? 'required|string|date' : 'sometimes|string|date';
        }
        foreach (['inserted_by', 'updated_by'] as $field) {
            $rules[$field] = $details ? 'required|uuid' : 'sometimes|uuid';
        }

        return $rules;
    }
}
