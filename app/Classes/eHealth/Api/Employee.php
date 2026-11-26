<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest;
use App\Classes\eHealth\EHealthResponse;
use App\Core\Arr;
use App\Enums\Status;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Exceptions\EHealth\EHealthResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class Employee extends EHealthRequest
{
    protected const string EMPLOYMENT_DATE_FORMAT = 'date_format:Y-m-d,Y-m-d\TH:i:s.v\Z';

    public const string URL = '/api/employees';

    /**
     * If true, groups the response by entities associated with the employee, e.g., employee itself, party, educations, specialities, etc.
     */
    public bool $groupByEntities = false;

    /**
     * Gets a single page of employees from E-Health.
     * It now attaches a validator to process the response.
     *
     * @param  array  $filters  An associative array of query parameters to filter the results.
     * @param  int  $page  The page number to fetch.
     * @param  bool  $withIdentity  Retain division and tax identity for request matching, not bulk persistence.
     * @return PromiseInterface|EHealthResponse The EHealthResponse object containing the validated and transformed data.
     * @throws EHealthConnectionException
     */
    public function getMany(array $filters, int $page = 1, bool $withIdentity = false): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateMany($response, $withIdentity));
        $this->setDefaultPageSize();

        $mergedQuery = array_merge(
            $this->options['query'] ?? [],
            $filters,
            ['page' => $page]
        );

        return $this->get(self::URL, $mergedQuery);
    }

    /**
     * @throws EHealthConnectionException
     */
    public function getDetails(string $uuid, $query = null, bool $groupByEntities = false): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateDetails(...));
        $this->groupByEntities = $groupByEntities;

        return $this->get(self::URL . '/' . $uuid, $query);
    }

    /**
     * Deactivate an approved employee and validate the confirmed employment state.
     *
     * @param  string  $id  The UUID of the employee.
     * @param  string  $endDate  The end date in 'Y-m-d' format.
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthResponseException
     */
    public function deactivate(string $id, ?string $endDate = null, string $status = 'STOPPED'): PromiseInterface|EHealthResponse
    {
        $payload = [
            'status' => $status,
        ];

        // ENTERED_IN_ERROR must not include end_date (п.4.1.2).
        if ($status === 'STOPPED' && $endDate !== null && $endDate !== '') {
            $payload['end_date'] = $endDate;
        }

        $this->setValidator(fn (EHealthResponse $response): array => $this->validateDeactivation($response, $id, $status));

        return $this->patch(self::URL . '/' . $id . '/actions/deactivate', $payload);
    }

    /**
     * Validates the response for a list of employees.
     *
     * @param  EHealthResponse  $response  The response from the eHealth API.
     * @return array The validated and transformed data.
     */
    protected function validateMany(EHealthResponse $response, bool $withIdentity = false): array
    {
        $items = Validator::make($response->json() ?? [], ['data' => ['present', 'array', 'list'], 'data.*' => ['array']])
            ->validated()['data'];
        $transformedData = array_map(
            fn (array $item): array => $this->normalizeEmploymentStatus(self::replaceEHealthPropNames($item)),
            $items,
        );

        $rules = [
            '*' => 'required|array',
            '*.uuid' => 'required|uuid',
            '*.status' => ['required', $this->employmentStatusRule()],
            '*.position' => 'required|string',
            '*.employee_type' => 'required|string',
            '*.start_date' => ['required', self::EMPLOYMENT_DATE_FORMAT],
            '*.end_date' => ['nullable', self::EMPLOYMENT_DATE_FORMAT],
            '*.is_active' => 'sometimes|required|boolean',
            '*.division_id' => 'sometimes|nullable|uuid',
            '*.party' => 'required|array',
            '*.party.uuid' => 'required|uuid',
            '*.party.no_tax_id' => 'required|boolean',
            '*.party.first_name' => 'required|string',
            '*.party.last_name' => 'required|string',
            '*.party.second_name' => 'nullable|string',
            '*.doctor' => 'sometimes|array',
            '*.doctor.specialities' => 'required_with:*.doctor|array',
            '*.doctor.specialities.*.speciality' => 'required_with:*.doctor|string',
            '*.doctor.specialities.*.speciality_officio' => 'required_with:*.doctor|boolean',
            '*.doctor.specialities.*.attestation_date' => 'required_with:*.doctor|date_format:Y-m-d',
            '*.doctor.specialities.*.attestation_name' => 'required_with:*.doctor|string',
            '*.doctor.specialities.*.certificate_number' => 'required_with:*.doctor|string',
            '*.doctor.specialities.*.level' => 'string',
        ];

        if ($withIdentity) {
            $rules += [
                '*.division' => 'sometimes|nullable|array',
                '*.division.uuid' => 'sometimes|uuid',
                '*.party.tax_id' => 'sometimes|nullable|string',
            ];
        }

        $validator = Validator::make($transformedData, $rules);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'EHealth Employee validation failed: ' . implode(', ', $validator->errors()->all())
            );
        }

        return array_map($this->normalizeEmploymentDates(...), $validator->validated());
    }

    /**
     * Validates the response for a single employee.
     *
     * @param  EHealthResponse  $response  The response from the eHealth API.
     * @return array The validated and transformed data.
     */
    protected function validateDetails(EHealthResponse $response): array
    {
        $transformedData = $this->normalizeEmploymentStatus(self::replaceEHealthPropNames($response->getData()));

        $employeeTypeKey = strtolower($transformedData['employee_type'] ?? '');
        $medTypes = implode(',', config('ehealth.medical_employees', []));

        $rules = [
            'uuid' => 'required|uuid',
            'status' => ['required', $this->employmentStatusRule()],
            'position' => 'required|string',
            'employee_type' => 'required|string',
            'start_date' => ['required', self::EMPLOYMENT_DATE_FORMAT],
            'end_date' => ['nullable', self::EMPLOYMENT_DATE_FORMAT],
            'is_active' => 'required|boolean',

            'division' => 'sometimes|nullable|array',

            'party' => 'required|array',
            'party.uuid' => 'required|uuid',
            'party.no_tax_id' => 'required|boolean',
            'party.tax_id' => 'required|string',
            'party.first_name' => 'required|string',
            'party.last_name' => 'required|string',
            'party.second_name' => 'nullable|string',
            'party.about_myself' => 'sometimes|nullable|string',
            'party.working_experience' => 'sometimes|nullable|integer',
            'party.gender' => 'sometimes|nullable|string',
            'party.birth_date' => 'required|date_format:Y-m-d',
            'party.verification_status' => 'required|string|in:VERIFIED,VERIFICATION_NEEDED,NOT_VERIFIED',
            'party.declaration_count' => 'required|integer',
            'party.declaration_limit' => 'required|integer',

            'party.phones' => 'required|array|min:1',
            'party.phones.*.type' => 'required|string',
            'party.phones.*.number' => 'required|string',

            'party.documents' => 'required|array|min:1',
            'party.documents.*.type' => 'required|string',
            'party.documents.*.number' => 'required|string',
            'party.documents.*.issued_by' => 'nullable|string',
            'party.documents.*.issued_at' => 'nullable|date_format:Y-m-d',
        ];

        if (!empty($employeeTypeKey)) {
            $rules[$employeeTypeKey] = 'required_if:employee_type,' . $medTypes . '|array';

            $rules["{$employeeTypeKey}.specialities"] = "nullable|array"; // TODO temporary fix for data inconsistency with eHealth
            $rules["{$employeeTypeKey}.specialities.*.speciality"] = "required|string";
            $rules["{$employeeTypeKey}.specialities.*.speciality_officio"] = "required|boolean";
            $rules["{$employeeTypeKey}.specialities.*.attestation_date"] = "required|date_format:Y-m-d";
            $rules["{$employeeTypeKey}.specialities.*.attestation_name"] = "required|string";
            $rules["{$employeeTypeKey}.specialities.*.certificate_number"] = "required|string";
            $rules["{$employeeTypeKey}.specialities.*.level"] = "required|string";
            $rules["{$employeeTypeKey}.specialities.*.qualification_type"] = "required|string";

            $rules["{$employeeTypeKey}.educations"] = "required_with:{$employeeTypeKey}|array|min:1";
            $rules["{$employeeTypeKey}.educations.*.city"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.country"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.degree"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.diploma_number"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.institution_name"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.speciality"] = "required|string";
            $rules["{$employeeTypeKey}.educations.*.issued_date"] = "nullable|date_format:Y-m-d";

            $rules["{$employeeTypeKey}.science_degree"] = 'sometimes|nullable|array';
            $rules["{$employeeTypeKey}.science_degree.country"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.city"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.degree"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.institution_name"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.diploma_number"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.speciality"] = "required_with:{$employeeTypeKey}.science_degree|string";
            $rules["{$employeeTypeKey}.science_degree.issued_date"] = 'nullable|date_format:Y-m-d';

            $rules["{$employeeTypeKey}.qualifications"] = 'sometimes|array';
            $rules["{$employeeTypeKey}.qualifications.*.type"] = "required_with:{$employeeTypeKey}.qualifications|string";
            $rules["{$employeeTypeKey}.qualifications.*.institution_name"] = "required_with:{$employeeTypeKey}.qualifications|string";
            $rules["{$employeeTypeKey}.qualifications.*.speciality"] = "required_with:{$employeeTypeKey}.qualifications|string";
            $rules["{$employeeTypeKey}.qualifications.*.issued_date"] = "nullable|date_format:Y-m-d"; // optional because of the sync with test eHealth instance
            $rules["{$employeeTypeKey}.qualifications.*.certificate_number"] = "nullable|string"; // optional because of the sync with test instance
            //            $rules["{$employeeTypeKey}.qualifications.*.valid_to"] = "nullable|date_format:Y-m-d|after_or_equal:{$employeeTypeKey}.qualifications.*.issued_date";
            $rules["{$employeeTypeKey}.qualifications.*.valid_to"] = "nullable|date_format:Y-m-d";
            $rules["{$employeeTypeKey}.qualifications.*.additional_info"] = 'nullable|string';
        }

        $validator = Validator::make($transformedData, $rules);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'EHealth Employee validation failed: ' . implode(', ', $validator->errors()->all())
            );
        }

        $validated = $this->normalizeEmploymentDates($validator->validated());

        if (!$this->groupByEntities) {
            return $validated;
        }

        $party = Arr::pull($validated, 'party', []);
        $documents = Arr::pull($party, 'documents', []);
        $phones = Arr::pull($party, 'phones', []);

        $doctorData = Arr::pull($validated, $employeeTypeKey, []);

        return [
            'employee' => $validated,
            'party' => $party,
            'documents' => $documents,
            'phones' => $phones,
            'educations' => $doctorData['educations'] ?? [],
            'specialities' => $doctorData['specialities'] ?? [],
            'qualifications' => $doctorData['qualifications'] ?? [],
            'scienceDegree' => $doctorData['science_degree'] ?? [],
            'division' => $validated['division'] ?? null,
        ];
    }

    protected function validateDeactivation(EHealthResponse $response, string $id, string $status): array
    {
        $data = $this->normalizeEmploymentStatus($response->getData());

        $data['id'] = is_string($data['id'] ?? null) ? strtolower($data['id']) : null;
        $validated = Validator::make($data, [
            'id' => ['required', 'uuid', Rule::in([strtolower($id)])],
            'status' => ['required', Rule::in([$status])],
            'is_active' => ['required', 'boolean'],
            'end_date' => ['nullable', self::EMPLOYMENT_DATE_FORMAT],
        ])->validated();

        return $this->normalizeEmploymentDates($validated);
    }

    protected function normalizeEmploymentDates(array $data): array
    {
        // Store calendar dates without shifting the day of an API timestamp.
        foreach (['start_date', 'end_date'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = substr($data[$key], 0, 10);
            }
        }

        return $data;
    }

    protected function normalizeEmploymentStatus(array $data): array
    {
        // Some eHealth schemas serialize this status with spaces.
        if (($data['status'] ?? null) === 'ENTERED IN ERROR') {
            $data['status'] = Status::ENTERED_IN_ERROR->value;
        }

        return $data;
    }

    protected function employmentStatusRule(): Enum
    {
        return Rule::enum(Status::class)->only(
            array_values(array_filter(Status::cases(), fn (Status $status): bool => $status->isEmploymentStatus()))
        );
    }

    /**
     * Replaces eHealth property names with the ones used in the application (e.g., id -> uuid).
     *
     * @param  array  $properties  Raw properties from a single API item.
     * @return array Properties with application-friendly names.
     */
    protected static function replaceEHealthPropNames(array $properties): array
    {
        $replaced = [];
        foreach ($properties as $name => $value) {
            switch ($name) {
                case 'id':
                    $replaced['uuid'] = $value;
                    break;
                case 'party':
                    $value['uuid'] = $value['id'];
                    unset($value['id']);
                    $replaced['party'] = $value;
                    break;
                case 'division':
                    if (is_array($value)) {
                        $value['uuid'] = $value['id'];
                        unset($value['id']);
                    }
                    $replaced['division'] = $value;
                    break;
                case 'legal_entity':
                    if (is_array($value)) {
                        $value['uuid'] = $value['id'];
                        unset($value['id']);
                    }
                    $replaced['legal_entity'] = $value;
                    break;
                default:
                    $replaced[$name] = $value;
                    break;
            }
        }

        return $replaced;
    }
}
