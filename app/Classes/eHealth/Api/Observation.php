<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Enums\Person\ObservationStatus;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Observation extends Request
{
    protected const string URL = '/api/patients';

    /**
     * Return an observation context record by IDs.
     *
     * @param  string  $patientUuid
     * @param  string  $episodeUuid
     * @param  array  $data
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getInEpisodeContext(
        string $patientUuid,
        string $episodeUuid,
        array $data = []
    ): PromiseInterface|EHealthResponse {
        return $this->get(self::URL . "/$patientUuid/episodes/$episodeUuid/observations", $data);
    }

    /**
     * Get observation by ID.
     *
     * @param  string  $patientId
     * @param  string  $observationId
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/observation/get-observation-by-id
     */
    public function getById(string $patientId, string $observationId): PromiseInterface|EHealthResponse
    {
        return $this->get(self::URL . "/$patientId/episodes/$observationId");
    }

    /**
     * Get a list of summary info about observations.
     *
     * @param  string  $patientId
     * @param  array{code?: string, issued_from?: string, issued_to?: string, page?: int, page_size?: int}  $query
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/patient-summary/get-observations
     */
    public function getSummary(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setDefaultPageSize();

        $mergedQuery = array_merge($this->options['query'], $query ?? []);

        return $this->get(self::URL . "/$patientId/summary/observations", $mergedQuery);
    }

    /**
     * Get a list of observations.
     *
     * @param  string  $patientId
     * @param  array{
     *     code?: string,
     *     encounter_id?: string,
     *     diagnostic_report_id?: string,
     *     episode_id?: string,
     *     issued_from?: string,
     *     issued_to?: string,
     *     device_id?: string,
     *     managing_organization_id?: string,
     *     specimen_id?: string,
     *     page?: int,
     *     page_size?: int
     * }  $query
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/observation/get-observations-by-searh-params
     */
    public function getBySearchParams(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateObservations(...));
        $this->setDefaultPageSize();

        $mergedQuery = array_merge($this->options['query'], $query ?? []);

        return $this->get(self::URL . "/$patientId/observations", $mergedQuery);
    }

    /**
     * Get a detail info about observation summary.
     *
     * @param  string  $patientId
     * @param  string  $id  Observation ID
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/patient-summary/get-observation-by-id
     */
    public function getSummaryById(string $patientId, string $id): PromiseInterface|EHealthResponse
    {
        return $this->get(self::URL . "/$patientId/summary/observations/$id");
    }

    /**
     * Validate observations response from eHealth API.
     *
     * @param  EHealthResponse  $response
     * @return array
     */
    protected function validateObservations(EHealthResponse $response): array
    {
        $replaced = [];
        foreach ($response->getData() as $data) {
            $replaced[] = self::replaceEHealthPropNames($data);
        }

        $rules = collect($this->observationValidationRules())
            ->mapWithKeys(static fn ($rule, $key) => ["*.$key" => $rule])
            ->toArray();

        $validator = Validator::make($replaced, $rules);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'Observation validation failed: ' . implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validate();
    }

    /**
     * Validation rules for observation data.
     *
     * @return array
     */
    protected function observationValidationRules(): array
    {
        return [
            'uuid' => ['required', 'string'],
            'status' => ['required', Rule::in(ObservationStatus::values())],

            'diagnostic_report' => ['nullable', 'array'],
            'diagnostic_report.identifier' => ['nullable', 'array'],
            'diagnostic_report.identifier.type' => ['nullable', 'array'],
            'diagnostic_report.identifier.type.coding' => ['nullable', 'array'],
            'diagnostic_report.identifier.type.coding.*.code' => ['nullable', 'string'],
            'diagnostic_report.identifier.type.coding.*.system' => ['nullable', 'string'],
            'diagnostic_report.identifier.value' => ['nullable', 'string'],

            'context' => ['nullable', 'array'],
            'context.identifier' => ['nullable', 'array'],
            'context.identifier.type' => ['nullable', 'array'],
            'context.identifier.type.coding' => ['nullable', 'array'],
            'context.identifier.type.coding.*.code' => ['nullable', 'string'],
            'context.identifier.type.coding.*.system' => ['nullable', 'string'],
            'context.identifier.value' => ['nullable', 'string'],

            'categories' => ['required', 'array'],
            'categories.*.coding' => ['required', 'array'],
            'categories.*.coding.*.code' => ['required', 'string'],
            'categories.*.coding.*.system' => ['required', 'string'],
            'categories.*.text' => ['nullable', 'string'],

            'code' => ['required', 'array'],
            'code.coding' => ['required', 'array'],
            'code.coding.*.code' => ['required', 'string'],
            'code.coding.*.system' => ['required', 'string'],
            'code.text' => ['nullable', 'string'],

            'effective_date_time' => ['nullable', 'string'],
            'effective_period' => ['nullable', 'array'],
            'effective_period.start' => ['nullable', 'string'],
            'effective_period.end' => ['nullable', 'string'],

            'issued' => ['required', 'date'],
            'ehealth_inserted_at' => ['required', 'date'],
            'ehealth_updated_at' => ['required', 'date'],
            'primary_source' => ['required', 'boolean'],

            'performer' => ['nullable', 'array'],
            'performer.identifier' => ['nullable', 'array'],
            'performer.identifier.type' => ['nullable', 'array'],
            'performer.identifier.type.coding' => ['nullable', 'array'],
            'performer.identifier.type.coding.*.code' => ['nullable', 'string'],
            'performer.identifier.type.coding.*.system' => ['nullable', 'string'],
            'performer.identifier.value' => ['nullable', 'string'],

            'report_origin' => ['nullable', 'array'],
            'report_origin.coding' => ['nullable', 'array'],
            'report_origin.coding.*.code' => ['nullable', 'string'],
            'report_origin.coding.*.system' => ['nullable', 'string'],
            'report_origin.text' => ['nullable', 'string'],

            'interpretation' => ['nullable', 'array'],
            'interpretation.coding' => ['nullable', 'array'],
            'interpretation.coding.*.code' => ['nullable', 'string'],
            'interpretation.coding.*.system' => ['nullable', 'string'],
            'interpretation.text' => ['nullable', 'string'],

            'comment' => ['nullable', 'string'],

            'body_site' => ['nullable', 'array'],
            'body_site.coding' => ['nullable', 'array'],
            'body_site.coding.*.code' => ['nullable', 'string'],
            'body_site.coding.*.system' => ['nullable', 'string'],
            'body_site.text' => ['nullable', 'string'],

            'method' => ['nullable', 'array'],
            'method.coding' => ['nullable', 'array'],
            'method.coding.*.code' => ['nullable', 'string'],
            'method.coding.*.system' => ['nullable', 'string'],
            'method.text' => ['nullable', 'string'],

            'components' => ['nullable', 'array'],
            'components.*.code' => ['required', 'array'],
            'components.*.code.coding' => ['required', 'array'],
            'components.*.code.coding.*.code' => ['required', 'string'],
            'components.*.code.coding.*.system' => ['required', 'string'],
            'components.*.code.text' => ['nullable', 'string'],

            'components.*.interpretation' => ['nullable', 'array'],
            'components.*.interpretation.coding' => ['nullable', 'array'],
            'components.*.interpretation.coding.*.code' => ['nullable', 'string'],
            'components.*.interpretation.coding.*.system' => ['nullable', 'string'],
            'components.*.interpretation.text' => ['nullable', 'string'],

            'components.*.reference_ranges' => ['nullable', 'array'],

            'components.*.value_codeable_concept' => ['nullable', 'array'],
            'components.*.value_codeable_concept.coding' => ['nullable', 'array'],
            'components.*.value_codeable_concept.coding.*.code' => ['nullable', 'string'],
            'components.*.value_codeable_concept.coding.*.system' => ['nullable', 'string'],
            'components.*.value_codeable_concept.coding.*.extension' => ['nullable', 'array'],
            'components.*.value_codeable_concept.text' => ['nullable', 'string'],

            'components.*.value_quantity' => ['nullable', 'array'],
            'components.*.value_quantity.value' => ['nullable', 'numeric'],
            'components.*.value_quantity.comparator' => ['nullable', 'string'],
            'components.*.value_quantity.unit' => ['nullable', 'string'],
            'components.*.value_quantity.system' => ['nullable', 'string'],
            'components.*.value_quantity.code' => ['nullable', 'string'],

            'components.*.value_string' => ['nullable', 'string'],
            'components.*.value_boolean' => ['nullable', 'boolean'],
            'components.*.value_date_time' => ['nullable', 'string'],

            'specimen' => ['nullable', 'array'],
            'specimen.identifier' => ['nullable', 'array'],
            'specimen.identifier.type' => ['nullable', 'array'],
            'specimen.identifier.type.coding' => ['nullable', 'array'],
            'specimen.identifier.type.coding.*.code' => ['nullable', 'string'],
            'specimen.identifier.type.coding.*.system' => ['nullable', 'string'],
            'specimen.identifier.value' => ['nullable', 'string'],

            'device' => ['nullable', 'array'],
            'device.identifier' => ['nullable', 'array'],
            'device.identifier.type' => ['nullable', 'array'],
            'device.identifier.type.coding' => ['nullable', 'array'],
            'device.identifier.type.coding.*.code' => ['nullable', 'string'],
            'device.identifier.type.coding.*.system' => ['nullable', 'string'],
            'device.identifier.value' => ['nullable', 'string'],

            'based_on' => ['nullable', 'array'],
            'based_on.identifier' => ['nullable', 'array'],
            'based_on.identifier.type' => ['nullable', 'array'],
            'based_on.identifier.type.coding' => ['nullable', 'array'],
            'based_on.identifier.type.coding.*.code' => ['nullable', 'string'],
            'based_on.identifier.type.coding.*.system' => ['nullable', 'string'],
            'based_on.identifier.value' => ['nullable', 'string'],

            'reference_ranges' => ['nullable', 'array'],
            'explanatory_letter' => ['nullable', 'string']
        ];
    }

    /**
     * Replace eHealth property names with the ones used in the application.
     * E.g., id => uuid, inserted_at => ehealth_inserted_at.
     */
    protected static function replaceEHealthPropNames(array $properties): array
    {
        $replaced = [];

        foreach ($properties as $name => $value) {
            $newName = match ($name) {
                'id' => 'uuid',
                'inserted_at' => 'ehealth_inserted_at',
                'updated_at' => 'ehealth_updated_at',
                default => $name
            };

            $replaced[$newName] = $value;
        }

        return $replaced;
    }
}
