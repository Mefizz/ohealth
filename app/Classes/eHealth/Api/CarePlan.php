<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanGetMany;
use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\MedicalEventsErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\CarePlan as CarePlanModel;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CarePlan extends Request
{
    protected const string URL = '/api/care_plans';

    /** Submit signed creation content. Validation returns details on 201 or a job receipt on 202. */
    public function create(string $patientId, array $payload, ?string $planId = null): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('create');
        $this->setValidator(fn (EHealthResponse $response): CarePlanDetails|array => $this->validateSubmission($response, $planId, $patientId));

        return $this->post("/api/patients/$patientId/care_plans", $payload);
    }

    /**
     * Get Care Plans by search parameters.
     *
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getMany(array $query = []): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('search');
        $this->setValidator($this->validateMany(...));

        return $this->get(self::URL, $query);
    }

    /**
     * Get a specific Care Plan by Patient ID and Care Plan ID.
     *
     * @param  string  $patientId
     * @param  string  $id
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getDetails(string $patientId, string $id, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('viewAny');
        $this->setValidator(fn (EHealthResponse $response): CarePlanDetails => $this->validateResponse($response, $id, $patientId));

        return $this->get("/api/patients/{$patientId}/care_plans/{$id}", $query);
    }

    /**
     * Cancel a Care Plan.
     * Note: This requires a Digital Signature (DS).
     * The payload must contain 'signed_content' which is the Care Plan object (without activities) + status_reason, signed by KEП.
     *
     * @param  string  $personId
     * @param  string  $id
     * @param  array  $payload
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function cancel(string $personId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('cancel');
        $this->setValidator(fn (EHealthResponse $response): CarePlanDetails|array => $this->validateSubmission($response, $id, $personId));

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
        $this->authorizeOperation('complete');
        $this->setValidator(fn (EHealthResponse $response): CarePlanDetails|array => $this->validateSubmission($response, $id, $personId));

        return $this->patch("/api/patients/$personId/care_plans/$id/actions/complete", $payload);
    }

    /**
     * Get Care Plans by search parameters.
     *
     * @param  string  $patientId
     * @param  array  $query
     * @return PromiseInterface|EHealthResponse
     */
    public function getBySearchParams(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('search');
        $this->setValidator(fn (EHealthResponse $response): CarePlanGetMany => $this->validateMany($response, $patientId));

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
        $this->authorizeOperation('getSummary');
        $this->setValidator(fn (EHealthResponse $response): CarePlanGetMany => $this->validateMany($response, $patientId));
        $this->setDefaultPageSize();

        $mergedQuery = array_merge($this->options['query'], $query);

        return $this->get("/api/patients/$patientId/summary/care_plans", $mergedQuery);
    }

    protected function authorizeOperation(string $ability): void
    {
        // Queue workers use their previously authorized eHealth bearer token, independently of a web session.
        if (Auth::check() || !filled($this->options['headers']['Authorization'] ?? null)) {
            Gate::authorize($ability, CarePlanModel::class);
        }
    }

    protected function errorTranslator(): ApiErrorTranslator
    {
        return new MedicalEventsErrorTranslator();
    }

    /** Validate the details response and its informing method. */
    protected function validateResponse(EHealthResponse $response, string $id, string $patientId): CarePlanDetails
    {
        Validator::make(['statusCode' => $response->getStatusCode()] + (array) $response->json(), [
            'statusCode' => 'required|in:200',
            'data' => 'required|array',
            'urgent' => 'required|array',
            'urgent.informing_method_current' => 'required|array',
            'urgent.informing_method_current.type' => 'required|in:OTP,OFFLINE,THIRD_PERSON',
            'urgent.informing_method_current.phone_number' => 'sometimes|string',
        ])->validate();

        $data = $response->getData();
        if (array_is_list($data) && count($data) === 1 && is_array($data[0])) {
            $data = $data[0];
        }

        return $this->validateDetails($data, $id, $patientId);
    }

    /** Validate the short records used by patient lists and requisition search. */
    protected function validateMany(EHealthResponse $response, ?string $patientId = null): CarePlanGetMany
    {
        Validator::make(['statusCode' => $response->getStatusCode()] + (array) $response->json(), [
            'statusCode' => 'required|in:200',
            'data' => 'present|array|list',
            'paging' => 'sometimes|array',
            'paging.page' => 'required_with:paging|integer|min:1',
            'paging.page_size' => 'sometimes|integer|min:1',
            'paging.total_pages' => 'sometimes|integer|min:0',
            'paging.total_entries' => 'sometimes|integer|min:0',
        ])->validate();

        $rules = $this->validationRules(details: false);
        if ($patientId === null) {
            $rules += [
                'identity' => 'required|array',
                'identity.last_name' => 'required|string',
                'identity.first_name' => 'required|string',
                'identity.second_name' => 'present|string',
                'identity.gender' => 'required|in:MALE,FEMALE',
                'identity.age' => 'required|numeric',
            ];
        } else {
            $rules['subject.identifier.value'] .= '|in:'.$patientId;
        }

        $itemRules = collect($rules)->mapWithKeys(
            static fn (string $rule, string $key): array => ["*.$key" => str_replace('required_with:', 'required_with:*.', $rule)]
        )->all();
        $validator = Validator::make($response->getData(), ['*' => 'required|array', ...$itemRules]);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error('CarePlan list validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        return new CarePlanGetMany($validator->validate());
    }

    /** Validate CarePlan details carried by a processed job or a synchronous response. */
    public function validateDetails(array $data, ?string $id = null, ?string $patientId = null): CarePlanDetails
    {
        $rules = $this->validationRules();
        if ($id !== null) {
            $rules['id'] .= '|in:'.$id;
        }
        if ($patientId !== null) {
            $rules['subject.identifier.value'] .= '|in:'.$patientId;
        }
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error('CarePlan details validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        return new CarePlanDetails($validator->validate(), $data);
    }

    /** Validate the resulting plan on 201 or the processing receipt on 202. */
    protected function validateSubmission(EHealthResponse $response, ?string $id, string $patientId): CarePlanDetails|array
    {
        if ($response->getStatusCode() === 201) {
            return $this->validateDetails($response->getData(), $id, $patientId);
        }

        Validator::make(['statusCode' => $response->getStatusCode()], ['statusCode' => 'required|in:202'])->validate();
        $data = Job::validateSubmissionResponse($response);
        Validator::make($data, ['status' => 'required|in:pending,processing,accepted,queued'])->validate();

        return $data;
    }

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
            $rules[$field . '.coding'] = 'required_with:' . $field . '|array|min:1';
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

        // eHealth can return an organization display name without its identifier.
        $rules['managing_organization.identifier'] = 'sometimes|array';

        foreach (['inserted_at', 'updated_at'] as $field) {
            $rules[$field] = $details ? 'required|string|date' : 'sometimes|string|date';
        }
        foreach (['inserted_by', 'updated_by'] as $field) {
            $rules[$field] = $details ? 'required|uuid' : 'sometimes|uuid';
        }

        return $rules;
    }
}
