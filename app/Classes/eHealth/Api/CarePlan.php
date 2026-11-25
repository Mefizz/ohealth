<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\Api\Job;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanDetails;
use App\Classes\eHealth\Api\Responses\Collections\CarePlanGetMany;
use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Errors\MedicalEventsErrorTranslator;
use App\Contracts\ApiErrorTranslator;
use App\Core\Arr;
use App\Dto\CarePlan\Ehealth as CreateContent;
use App\Models\CarePlan as CarePlanModel;
use ArrayObject;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CarePlan extends Request
{
    protected const string URL = '/api/care_plans';

    /** Build the native content required by the array-based signature provider. */
    public function prepareCreateContent(array $resource): array
    {
        Validator::make($resource, [
            'id' => 'required|uuid',
            'title' => 'required|string',
            'category.coding' => 'required|array|min:1',
            'period.start' => 'required|date',
            'period.end' => 'sometimes|nullable|date|after_or_equal:period.start',
            'encounter.identifier.value' => 'required|uuid',
            'author.identifier.value' => 'required|uuid',
            'addresses' => 'required|array|min:1',
            'termsOfService.coding' => 'required|array|min:1',
            'basedOn' => 'sometimes|nullable|array',
            'partOf' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string',
            'note' => 'sometimes|nullable|string',
            'supportingInfo' => 'sometimes|nullable|array',
            'supportingInfo.*.identifier.value' => 'required|uuid',
            'supportingInfo.*.identifier.type.coding' => 'required|array|min:1',
            'supportingInfo.*.identifier.type.coding.*.code' => 'required|in:episode_of_care,procedure,diagnostic_report',
            'contributor' => 'sometimes|nullable|array',
            'contributor.*.identifier.value' => 'required|uuid',
            'informWith' => 'sometimes|nullable|array',
            'informWith.authMethodId' => 'sometimes|nullable|uuid',
        ])->validate();

        $content = objectMapper()->map(new ArrayObject($resource), CreateContent::class);

        return $this->omitEmpty(Arr::toSnakeCase(get_object_vars($content)));
    }

    /** Submit signed creation content. Validation returns details on 201 or a job receipt on 202. */
    public function create(string $patientId, array $payload, ?string $planId = null): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('create');
        $this->setValidator(fn (EHealthResponse $response) => $this->validateSubmission($response, $planId, $patientId));

        return $this->post("/api/patients/$patientId/care_plans", $payload);
    }

    /** Search by requisition. Validation returns a collection of short CarePlan entries with patient identity. */
    public function getMany(array $query = []): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('getMany');
        $this->setValidator($this->validateRequisition(...));

        return $this->get(self::URL, $query);
    }

    /** Read a full plan by UUID, retaining native keys for callers that sign an eHealth snapshot. */
    public function getDetails(
        string $patientId,
        string $id,
        array $query = [],
        bool $preserveApiKeys = false,
        bool $typed = false
    ): PromiseInterface|EHealthResponse {
        $this->authorizeOperation('getDetails');
        $this->setValidator(function (EHealthResponse $response) use ($id, $patientId, $preserveApiKeys, $typed): CarePlanDetails|array {
            Validator::make($response->json(), [
                'urgent' => 'sometimes|array',
                'urgent.informing_method_current' => 'required_with:urgent|array',
                'urgent.informing_method_current.type' => 'required_with:urgent.informing_method_current|in:OTP,OFFLINE,THIRD_PERSON',
                'urgent.informing_method_current.phone_number' => 'sometimes|string',
            ])->validate();
            Validator::make(['statusCode' => $response->getStatusCode()], ['statusCode' => 'required|in:200'])->validate();
            $details = $this->validateResource($response->getData(), $id, $patientId);

            return $typed ? $details : ($preserveApiKeys ? $details->native : $details->toResource());
        });

        return $this->get("/api/patients/$patientId/care_plans/$id", $query);
    }

    /** Submit the signed CarePlan snapshot with the cancellation reason. */
    public function cancel(string $personId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('cancel');
        $this->setValidator(fn (EHealthResponse $response) => $this->validateSubmission($response, $id, $personId));

        return $this->patch("/api/patients/$personId/care_plans/$id/actions/cancel", $payload);
    }

    /** Complete a plan with the documented unsigned status reason. */
    public function complete(string $personId, string $id, array $payload): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('complete');
        $this->setValidator(fn (EHealthResponse $response) => $this->validateSubmission($response, $id, $personId));

        return $this->patch("/api/patients/$personId/care_plans/$id/actions/complete", $payload);
    }

    /** Get short plans for one patient. */
    public function getBySearchParams(string $patientId, array $query = [], bool $typed = false): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('getBySearchParams');
        $this->setValidator(function (EHealthResponse $response) use ($patientId, $typed): CarePlanGetMany|array {
            $entries = $this->validateMany($response, $patientId);

            return $typed ? $entries : $entries->toBase()->map(
                static fn (CarePlanDetails $entry): array => $entry->toResource()
            )->all();
        });

        return $this->get("/api/patients/$patientId/care_plans", $query);
    }

    /** Get short plans in the patient summary using the patient-summary scope. */
    public function getSummary(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->authorizeOperation('getSummary');
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateMany($response, $patientId)
            ->toBase()->map(static fn (CarePlanDetails $entry): array => $entry->toResource())->all());
        $this->setDefaultPageSize();

        return $this->get("/api/patients/$patientId/summary/care_plans", array_replace($this->options['query'], $query));
    }

    /** Apply the same full-resource contract to synchronous details and embedded job details. */
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

    /** Resolve embedded details or fetch the authoritative record for a processed job. */
    public function confirmedDetails(array $job, string $id, string $patientId): CarePlanDetails
    {
        $result = $job['response_data'] ?? ($job['result'] ?? ($job['response']['body'] ?? ($job['data'] ?? [])));
        if (is_array($result)) {
            $result = array_is_list($result) ? ($result[0] ?? []) : $result;
            $result = $result['data'] ?? $result;
            if (is_array($result) && isset(
                $result['id'], $result['subject'], $result['period'], $result['category'],
                $result['addresses'], $result['requisition'], $result['status_history'], $result['updated_at']
            )) {
                return $this->validateResource($result, $id, $patientId);
            }
        }

        return $this->getDetails($patientId, $id, typed: true)->validate();
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

    protected function validateSubmission(EHealthResponse $response, ?string $id, string $patientId): Collection
    {
        if ($response->getStatusCode() === 201) {
            return $this->validateResource($response->getData(), $id, $patientId);
        }

        Validator::make(['statusCode' => $response->getStatusCode()], ['statusCode' => 'required|in:202'])->validate();
        $data = Job::validateSubmissionResponse($response);
        Validator::make($data, ['status' => 'required|in:pending,processing,accepted,queued'])->validate();

        return collect($data);
    }

    protected function validateMany(EHealthResponse $response, string $patientId): CarePlanGetMany
    {
        $rules = $this->validationRules(details: false);
        $rules['subject.identifier.value'] .= '|in:'.$patientId;

        return $this->validateList($response, $rules);
    }

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

    protected function validateList(EHealthResponse $response, array $itemRules): CarePlanGetMany
    {
        Validator::make(['statusCode' => $response->getStatusCode()], ['statusCode' => 'required|in:200'])->validate();
        $rules = collect($itemRules)->mapWithKeys(
            static fn (string $rule, string $key): array => ["data.*.$key" => str_replace('required_with:', 'required_with:data.*.', $rule)]
        )->all();
        Validator::make($response->json(), [
            'data' => 'present|array|list',
            'data.*' => 'required|array',
            'paging' => 'sometimes|array',
            'paging.page' => 'required_with:paging|integer|min:1',
            'paging.page_size' => 'sometimes|integer|min:1',
            'paging.total_pages' => 'sometimes|integer|min:0',
            'paging.total_entries' => 'sometimes|integer|min:0',
            ...$rules,
        ])->validate();

        return new CarePlanGetMany($response->getData());
    }

    /** Omit empty optional fields while preserving false, zero and valid JSON list indexes. */
    protected function omitEmpty(array $data): array
    {
        $data = array_map(fn (mixed $value): mixed => is_array($value) ? $this->omitEmpty($value) : $value, $data);
        $filtered = array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return array_is_list($data) ? array_values($filtered) : $filtered;
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

        foreach (['inserted_at', 'updated_at'] as $field) {
            $rules[$field] = $details ? 'required|string|date' : 'sometimes|string|date';
        }
        foreach (['inserted_by', 'updated_by'] as $field) {
            $rules[$field] = $details ? 'required|uuid' : 'sometimes|uuid';
        }

        return $rules;
    }
}
