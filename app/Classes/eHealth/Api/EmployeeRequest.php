<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest;
use App\Classes\eHealth\EHealthResponse;
use App\Classes\eHealth\Api\Responses\Collections\EmployeeRequestDetails;
use App\Classes\eHealth\Api\Responses\Collections\EmployeeRequestGetMany;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use GuzzleHttp\Promise\PromiseInterface;
use App\Exceptions\EHealth\EHealthConnectionException;
use Illuminate\Support\Arr;
use App\Models\Division;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeRequest extends EHealthRequest
{
    /**
     * The API endpoint for employee requests.
     */
    public const string ENDPOINT = '/api/v2/employee_requests';

    /**
     * Creates a new Employee Request in eHealth using a signed data payload.
     * This is the primary action method for this class.
     *
     * @param  string  $signedContent  The base64 encoded signed string.
     * @return array The response data from eHealth on success.
     * @throws EHealthConnectionException|RuntimeException
     */
    public function create(string $signedContent): array
    {
        $requestBody = [ 'signed_content' => $signedContent, 'signed_content_encoding' => 'base64' ];

        $response = $this->post(self::ENDPOINT, $requestBody);

        return [
            'id' => $response->json('data.id'),
            'ehealth_response' => $response->json(),
        ];
    }

    /**
     * Transforms a source data array into a structured, partitioned array.
     *
     * This method takes a source array, typically from a Revision's data,
     * and reshapes it into a consistent structure with keys like 'employee',
     * 'party', 'documents', etc., making it ready for repository processing.
     *
     * @param  array  $sourceData  The source data array containing all necessary information.
     * @return array A structured array partitioned into logical keys.
     */
    public function mapCreate(array $sourceData): array
    {
        $partyData = $sourceData['party'] ?? [];

        $professionalData = [];
        $professionalKey = strtolower(Arr::get($sourceData, 'employee_request_data.employee_type', 'doctor'));
        foreach (array_unique([$professionalKey, 'doctor', 'med_admin', 'med_coordinator', 'pharmacist', 'specialist', 'assistant', 'laborant']) as $key) {
            if (isset($sourceData[$key])) {
                $professionalData = $sourceData[$key];
                break;
            }
        }

        // 2.  sometimes the API returns education (singular) instead of educations (plural)
        $educations = $professionalData['educations']
            ?? ($professionalData['education'] ?? null);

        return [
            'employee' => Arr::get($sourceData, 'employee_request_data', []),
            'party' => Arr::except($partyData, ['documents', 'phones', 'email']),
            'documents' => $sourceData['documents'] ?? null,
            'phones' => $sourceData['phones'] ?? null,

            // We use the found universal data
            'educations' => $educations,
            'specialities' => $professionalData['specialities'] ?? null,
            'qualifications' => $professionalData['qualifications'] ?? null,
            'science_degree' => $professionalData['science_degree'] ?? null,
            'has_science_degree' => array_key_exists('science_degree', $professionalData),
        ];
    }

    /**
     * Gets employee requests from E-Health.
     * Handles both paginated listing and lookup by a single ID.
     *
     * @param  array  $filters  An associative array of query parameters to filter the results.
     * @param  int|null  $page  The page number to fetch. Pass null when filtering by a single 'id'.
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException|EHealthValidationException|EHealthResponseException
     */
    public function getMany(array $filters, ?int $page = 1): PromiseInterface|EHealthResponse
    {
        $getEndpoint = '/api/employee_requests';

        $this->setValidator(fn (EHealthResponse $response): EmployeeRequestGetMany => $this->validateMany(
            $response,
            !isset($filters['id']),
            isset($filters['id']) ? null : $page,
        ));

        if (!isset($filters['id'])) {
            $this->setDefaultPageSize();
            if ($page !== null) {
                $filters['page'] = $page;
            }
        }

        $query = array_merge(
            $this->options['query'] ?? [],
            $filters
        );

        return $this->get($getEndpoint, $query);
    }

    /**
     * Retrieves full details of a specific Employee Request by UUID.
     * The user-token detail contract does not promise the resulting employee UUID.
     *
     * @param  string  $uuid
     * @return EHealthResponse|PromiseInterface
     * @throws EHealthConnectionException
     */
    public function getDetails(string $uuid): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validate(...));

        $getEndpoint = '/api/employee_requests';
        $url = $getEndpoint . '/' . $uuid;

        return $this->get($url);
    }

    /**
     * Validates the response for a list of employee requests.
     *
     * @param  EHealthResponse  $response  The response from the eHealth API.
     * @return array The validated data.
     */
    protected function validateMany(EHealthResponse $response, bool $paginated = true, ?int $page = null): EmployeeRequestGetMany
    {
        $envelopeRules = [
            'data' => 'present|array|list',
            'data.*' => 'required|array',
            'data.*.id' => 'required|uuid',
        ];
        if ($paginated || $response->json('paging') !== null) {
            $envelopeRules += [
                'paging' => 'required|array',
                'paging.page_number' => 'required|integer|min:1',
                'paging.page_size' => 'required|integer|min:1|max:300',
                'paging.total_entries' => 'required|integer|min:0',
                'paging.total_pages' => 'required|integer|min:0',
            ];
            if ($page !== null) {
                $envelopeRules['paging.page_number'] = ['required', 'integer', 'min:1', Rule::in([$page])];
            }
        }
        Validator::make($response->json(), $envelopeRules)->validate();

        $transformedData = [];
        foreach ($response->getData() as $item) {
            $transformedData[] = $this->replaceEHealthPropNames($item);
        }

        $validator = Validator::make($transformedData, [
            '*' => 'required|array',
            '*.uuid' => 'required|uuid',
            '*.status' => 'required|string|in:NEW,REJECTED,APPROVED,EXPIRED',
            '*.inserted_at' => 'required|date',
            '*.edrpou' => 'required|string',
            '*.legal_entity_name' => 'required|string',
            '*.no_tax_id' => 'sometimes|boolean',
            '*.first_name' => 'required|string',
            '*.last_name' => 'required|string',
            '*.second_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'EHealth EmployeeRequest validation failed: ' . implode(', ', $validator->errors()->all())
            );
            $validator->validate();
        }

        return new EmployeeRequestGetMany($validator->validated());
    }

    protected function validate(EHealthResponse $response): EmployeeRequestDetails
    {
        Validator::make($response->json(), ['data' => 'required|array', 'data.id' => 'required|uuid'])->validate();
        $transformedData = $this->replaceEHealthPropNames($response->getData());

        $rules = [
            'employee_type' => 'required|string',
            'division_uuid' => 'nullable|uuid',
            'uuid' => 'required|uuid',
            'inserted_at' => 'sometimes|date',
            'created_at' => 'sometimes|date',
            'legal_entity_uuid' => 'sometimes|uuid',
            'party' => 'sometimes|array|required_array_keys:first_name,last_name,birth_date,gender',
            'party.first_name' => 'required_with:party|string',
            'party.last_name' => 'required_with:party|string',
            'party.birth_date' => 'required_with:party|date',
            'party.gender' => 'required_with:party|string',
            'party.second_name' => 'sometimes|nullable|string',
            'party.tax_id' => 'sometimes|nullable|string',
            'party.no_tax_id' => 'sometimes|boolean',
            'party.email' => 'sometimes|string',
            'party.working_experience' => 'sometimes|numeric',
            'party.about_myself' => 'sometimes|nullable|string',
            'party.documents' => 'sometimes|array|list',
            'party.documents.*' => 'required|array',
            'party.documents.*.type' => 'required|string',
            'party.documents.*.number' => 'required|string',
            'party.documents.*.issued_by' => 'sometimes|nullable|string',
            'party.documents.*.issued_at' => 'sometimes|nullable|date',
            'party.documents.*.expiration_date' => 'sometimes|nullable|date',
            'party.phones' => 'sometimes|array|list',
            'party.phones.*' => 'required|array',
            'party.phones.*.type' => 'required|string',
            'party.phones.*.number' => 'required|string',
            'email' => 'sometimes|string',
            'position' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'sometimes|nullable|date',
            'status' => 'required|string|in:NEW,REJECTED,APPROVED,EXPIRED',
            'updated_at' => 'sometimes|date',
        ];

        foreach (['doctor', 'med_admin', 'med_coordinator', 'pharmacist', 'specialist', 'assistant', 'laborant'] as $professionalKey) {
            $rules[$professionalKey] = 'sometimes|array';
            foreach (['educations', 'specialities'] as $relation) {
                $presence = $professionalKey === 'doctor' && array_key_exists($professionalKey, $transformedData) ? 'present' : 'sometimes';
                $rules["$professionalKey.$relation"] = "$presence|array|list";
            }
            $rules["$professionalKey.qualifications"] = 'sometimes|array|list';
            $rules["$professionalKey.science_degree"] = 'sometimes|nullable|array|required_array_keys:country,city,degree,institution_name,diploma_number,speciality';

            foreach ([
                'educations' => ['country', 'city', 'institution_name', 'diploma_number', 'degree', 'speciality'],
                'qualifications' => ['type', 'institution_name', 'speciality', 'issued_date', 'certificate_number'],
                'specialities' => ['speciality', 'speciality_officio', 'level', 'qualification_type', 'attestation_name', 'attestation_date', 'certificate_number'],
                'science_degree' => ['country', 'city', 'degree', 'institution_name', 'diploma_number', 'speciality'],
            ] as $relation => $requiredFields) {
                $path = "$professionalKey.$relation" . ($relation === 'science_degree' ? '' : '.*');
                if ($relation !== 'science_degree') {
                    $rules[$path] = 'required|array';
                }
                foreach ($requiredFields as $field) {
                    $type = $field === 'speciality_officio' ? 'boolean' : (str_ends_with($field, '_date') ? 'date' : 'string');
                    $rules["$path.$field"] = "required_with:$path|$type";
                }
            }

            foreach (['educations.*.issued_date', 'qualifications.*.valid_to', 'specialities.*.valid_to_date', 'science_degree.issued_date'] as $field) {
                $rules["$professionalKey.$field"] = 'sometimes|nullable|date';
            }
            $rules["$professionalKey.qualifications.*.additional_info"] = 'sometimes|nullable|string';
        }

        $validator = Validator::make($transformedData, $rules);

        if ($validator->fails()) {
            Log::channel('e_health_errors')->error(
                'EHealth EmployeeRequest validation failed: ' . implode(', ', $validator->errors()->all())
            );
            $validator->validate();
        }

        return new EmployeeRequestDetails($validator->validated());
    }

    /**
     * Replaces eHealth property names with the ones used in the application (e.g., id -> uuid).
     *
     * @param  array  $properties  Raw properties from a single API item.
     * @return array Properties with application-friendly names.
     */
    protected function replaceEHealthPropNames(array $properties): array
    {
        $replaced = [];

        foreach ($properties as $name => $value) {
            switch ($name) {
                case 'id':
                    $replaced['uuid'] = $value;
                    break;
                case 'legal_entity_id':
                    $replaced['legal_entity_uuid'] = $value;
                    break;
                case 'division_id':
                    $replaced['division_uuid'] = $value;
                    break;
                case 'inserted_at':
                    $replaced['inserted_at'] = $value;
                    $replaced['created_at'] = $value;
                    break;
                case 'party':
                    $replaced['party'] = $value;
                    if (is_array($value) && array_key_exists('email', $value)) {
                        $replaced['email'] = $value['email'];
                    }
                    break;
                default:
                    $replaced[$name] = $value;
            }
        }

        return $replaced;
    }

    /**
     * Builds the eHealth-compliant payload from the application's internal data structure.
     *
     * @param  array  $nestedData  Data from the Revision model.
     * @return array The structured payload ready for signing and sending to eHealth.
     */
    public function schemaCreate(array $nestedData): array
    {
        $localDivisionId = Arr::get($nestedData, 'employee_request_data.division_id');
        $divisionUuid = $localDivisionId ? Division::find($localDivisionId)?->uuid : null;

        $partyPayload = Arr::only($nestedData['party'] ?? [], [
            'first_name', 'last_name', 'second_name', 'birth_date', 'gender',
            'tax_id', 'email', 'about_myself'
        ]);

        $partyPayload['no_tax_id'] = (bool) Arr::get($nestedData, 'party.no_tax_id');

        $workingExperience = Arr::get($nestedData, 'party.working_experience');
        if ($workingExperience !== null && $workingExperience !== '') {
            $partyPayload['working_experience'] = (int) $workingExperience;
        }

        $partyPayload['documents'] = $nestedData['documents'] ?? [];
        $partyPayload['phones'] = $nestedData['phones'] ?? [];

        $payload = [
            'position' => Arr::get($nestedData, 'employee_request_data.position'),
            'start_date' => Arr::get($nestedData, 'employee_request_data.start_date'),
            'end_date' => Arr::get($nestedData, 'employee_request_data.end_date'),
            'employee_type' => Arr::get($nestedData, 'employee_request_data.employee_type'),
            'division_id' => $divisionUuid,
            'legal_entity_id' => legalEntity()->uuid,
            'status' => 'NEW',
            'party' => $partyPayload,
        ];

        $medTypes = config('ehealth.medical_employees', []);
        $employeeType = Arr::get($nestedData, 'employee_request_data.employee_type');

        if (in_array($employeeType, $medTypes, true)) {
            // 1. We determine the correct key that eHealth expects
            $targetKey = match ($employeeType) {
                'MED_COORDINATOR' => 'med_coordinator',
                'MED_ADMIN' => 'med_admin',
                'PHARMACIST' => 'pharmacist',
                'SPECIALIST' => 'specialist',
                'ASSISTANT' => 'assistant',
                'LABORANT' => 'laborant',
                default => 'doctor',
            };

            // 2.We get the data. They can be keyed 'doctor' (if so saved in the database)
            $doctorData = Arr::get($nestedData, $targetKey) ?? Arr::get($nestedData, 'doctor');

            // 3.Add to payload only if there is data
            if (!empty($doctorData)) {
                $payload[$targetKey] = $doctorData;
            }
        }

        if ($employeeUuid = Arr::get($nestedData, 'employee_uuid')) {
            $payload['employee_id'] = $employeeUuid;
        }

        return ['employee_request' => $this->removeEmptyValuesRecursively($payload)];
    }

    /**
     * Recursively removes empty values from an array.
     */
    private function removeEmptyValuesRecursively(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeEmptyValuesRecursively($value);
            }
        }

        return array_filter($array, static function ($value) {
            return !is_null($value) && $value !== '' && $value !== [];
        });
    }

    public function schemaRequest(): array
    {
        $phoneDefinition = [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['MOBILE', 'LANDLINE'],
                ],
                'number' => [
                    'type' => 'string',
                    'pattern' => '^\+38[0-9]{10}$',
                ],
            ],
            'required' => ['type', 'number'],
            'additionalProperties' => false,
        ];

        $documentDefinition = [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => [
                        'PASSPORT',
                        'NATIONAL_ID',
                        'COMPLEMENTARY_PROTECTION_CERTIFICATE',
                        'PERMANENT_RESIDENCE_PERMIT',
                        'REFUGEE_CERTIFICATE',
                        'TEMPORARY_CERTIFICATE',
                        'TEMPORARY_PASSPORT',
                    ],
                ],
                'number' => ['type' => 'string'],
            ],
            'required' => ['type', 'number'],
            'additionalProperties' => false,
        ];

        $educationDefinition = [
            'type' => 'object',
            'properties' => [
                'country' => ['type' => 'string', 'enum' => ['UA']],
                'city' => ['type' => 'string'],
                'institution_name' => ['type' => 'string'],
                'issued_date' => ['type' => 'string'],
                'diploma_number' => ['type' => 'string'],
                'degree' => ['type' => 'string', 'enum' => ['Молодший спеціаліст', 'Бакалавр', 'Спеціаліст', 'Магістр']],
                'speciality' => ['type' => 'string'],
            ],
            'required' => ['country', 'city', 'institution_name', 'diploma_number', 'degree', 'speciality'],
            'additionalProperties' => false,
        ];

        $qualificationDefinition = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['Інтернатура', 'Спеціалізація', 'Передатестаційний цикл', 'Тематичне вдосконалення', 'Курси інформації', 'Стажування']],
                'institution_name' => ['type' => 'string'],
                'speciality' => ['type' => 'string'],
                'issued_date' => ['type' => 'string', 'format' => 'date'],
                'certificate_number' => ['type' => 'string'],
                'valid_to' => ['type' => 'string', 'format' => 'date'],
                'additional_info' => ['type' => 'string'],
            ],
            'required' => ['type', 'institution_name', 'speciality'],
            'additionalProperties' => false,
        ];

        $specialityDefinition = [
            'type' => 'object',
            'properties' => [
                'speciality' => ['type' => 'string', 'enum' => ['Терапевт', 'Педіатр', 'Сімейний лікар']],
                'speciality_officio' => ['type' => 'boolean'],
                'level' => ['type' => 'string', 'enum' => ['Друга категорія', 'Перша категорія', 'Вища категорія']],
                'qualification_type' => ['type' => 'string', 'enum' => ['Присвоєння', 'Підтвердження']],
                'attestation_name' => ['type' => 'string'],
                'attestation_date' => ['type' => 'string', 'format' => 'date'],
                'valid_to_date' => ['type' => 'string', 'format' => 'date'],
                'certificate_number' => ['type' => 'string'],
            ],
            'required' => ['speciality', 'speciality_officio', 'level', 'qualification_type', 'attestation_name', 'certificate_number'],
            'additionalProperties' => false,
        ];

        $scienceDegreeDefinition = [
            'type' => 'object',
            'properties' => [
                'country' => ['type' => 'string', 'enum' => ['UA']],
                'city' => ['type' => 'string'],
                'degree' => ['type' => 'string', 'enum' => ['Доктор філософії', 'Кандидат наук', 'Доктор наук']],
                'institution_name' => ['type' => 'string'],
                'diploma_number' => ['type' => 'string'],
                'speciality' => ['type' => 'string', 'enum' => ['Терапевт', 'Педіатр', 'Сімейний лікар']],
                'issued_date' => ['type' => 'string', 'format' => 'date'],
            ],
            'required' => ['country', 'city', 'degree', 'institution_name', 'diploma_number', 'speciality'],
            'additionalProperties' => false,
        ];

        $partyDefinition = [
            'type' => 'object',
            'properties' => [
                'first_name' => ['type' => 'string'],
                'last_name' => ['type' => 'string'],
                'second_name' => ['type' => 'string'],
                'birth_date' => ['type' => 'string', 'format' => 'date'],
                'gender' => ['type' => 'string', 'enum' => ['MALE', 'FEMALE']],
                'no_tax_id' => ['type' => 'boolean'],
                'tax_id' => ['type' => 'string', 'pattern' => '^[1-9]([0-9]{7}|[0-9]{9})$'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'working_experience' => ['type' => 'integer'],
                'about_myself' => ['type' => 'string'],
                'documents' => [
                    'type' => 'array',
                    'items' => $documentDefinition,
                ],
                'phones' => [
                    'type' => 'array',
                    'items' => $phoneDefinition,
                ],
            ],
            'required' => ['first_name', 'last_name', 'birth_date', 'gender', 'tax_id', 'email', 'documents', 'phones'],
            'additionalProperties' => false,
        ];

        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'definitions' => [
                'phone' => $phoneDefinition,
                'document' => $documentDefinition,
                'education' => $educationDefinition,
                'qualification' => $qualificationDefinition,
                'speciality' => $specialityDefinition,
                'science_degree' => $scienceDegreeDefinition,
                'party' => $partyDefinition,
            ],
            'type' => 'object',
            'properties' => [
                'employee_request' => [
                    'type' => 'object',
                    'properties' => [
                        'legal_entity_id' => ['type' => 'string', 'pattern' => '^[0-9a-f]{8}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{12}$'],
                        'division_id' => ['type' => 'string', 'pattern' => '^[0-9a-f]{8}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{12}$'],
                        'employee_id' => [
                            'type' => 'string',
                            'pattern' => '^[0-9a-f]{8}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{4}(-?)[0-9a-f]{12}$',
                        ],
                        'position' => [
                            'type' => 'string',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => [
                                'NEW',
                            ],
                        ],
                        'employee_type' => [
                            'type' => 'string',
                            'enum' => [
                                'DOCTOR',
                                'HR',
                                'ADMIN',
                                'OWNER',
                            ],
                        ],

                        'party' => $partyDefinition,
                        'doctor' => [
                            'type' => 'object',
                            'properties' => [
                                'educations' => [
                                    'type' => 'array',
                                    'items' => $educationDefinition,
                                ],
                                'qualifications' => [
                                    'type' => 'array',
                                    'items' => $qualificationDefinition,
                                ],
                                'specialities' => [
                                    'type' => 'array',
                                    'items' => $specialityDefinition,
                                ],
                                'science_degree' => $scienceDegreeDefinition,
                            ],
                            'required' => [
                                'educations',
                                'specialities',
                            ],
                        ],
                    ],
                    'required' => [
                        'legal_entity_id',
                        'position',
                        'start_date',
                        'status',
                        'employee_type',
                        'party',
                    ],
                ],
            ],
            'required' => [
                'employee_request',
            ],
        ];
    }

}
