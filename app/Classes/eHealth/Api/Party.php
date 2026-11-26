<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest;
use App\Classes\eHealth\EHealthResponse;
use App\Enums\Party\VerificationStatus;
use App\Exceptions\EHealth\EHealthConnectionException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Handles API requests related to the eHealth 'Party' and 'Party Verification' endpoints.
 */
class Party extends EHealthRequest
{
    /**
     * The base URL for the party endpoints.
     */
    protected const string URL = '/api/parties';

    /**
     * Get list of party verification statuses.
     *
     * Scope is limited by legal_entity_id from the OAuth token — do not pass legal_entity_id
     * (schema rejects it as an additional property).
     *
     * @param  array  $filters  Query filters allowed by the eHealth schema (e.g. pagination helpers only).
     * @param  int  $page
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException
     *
     * @see https://ehealthcoreapi9145v1.docs.apiary.io/#reference/public.-medical-service-provider-integration-layer/employees/get-party-verification-statuses-list
     */
    public function getMany(array $filters = [], int $page = 1): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateMany(...));
        $this->setMapper($this->mapMany(...));
        $this->setDefaultPageSize();

        $mergedQuery = array_merge(
            $this->options['query'] ?? [],
            $filters,
            ['page' => $page]
        );

        return $this->get(self::URL . '/verifications', $mergedQuery);
    }

    /**
     * Maps (transforms) the validated data.
     * It now returns [party_uuid=>{full_item_object}]
     *
     * @param  array  $validatedData  The data that has passed validation.
     * @return array
     */
    protected function mapMany(array $validatedData): array
    {
        return collect($validatedData)->mapWithKeys(function ($item) {
            if (empty($item['party_id'])) {
                return [];
            }

            return [$item['party_id'] => $item];
        })->filter()->all();
    }

    /**
     * Get party verification details by party id.
     *
     * @param  string  $uuid  The UUID of the party.
     * @param  array|null  $query  Optional query parameters.
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException
     *
     * @see https://ehealthcoreapi9145v1.docs.apiary.io/#reference/public.-medical-service-provider-integration-layer/employees/get-party-verification-details
     */
    public function getDetails(string $uuid, ?array $query = null): PromiseInterface|EHealthResponse
    {
        $this->setValidator($this->validateDetails(...));

        return $this->get(self::URL . '/' . $uuid . '/verification', $query);
    }

    /**
     * Sends a request to update a party's verification status.
     *
     * @param  string  $uuid  The UUID of the party to update.
     * @param  array  $data  The data for the update request.
     * @return PromiseInterface|EHealthResponse
     * @throws EHealthConnectionException
     */
    public function update(string $uuid, array $data = []): PromiseInterface|EHealthResponse
    {
        $this->setValidator(fn (EHealthResponse $response): array => $this->validateDetails($response, updating: true));

        return $this->patch(self::URL . '/' . $uuid . '/verification', $data);
    }

    /**
     * Validates the response for a single party's verification details.
     *
     * @param  EHealthResponse  $response  The response from the eHealth API.
     * @return array
     * @throws ValidationException
     */
    protected function validateDetails(EHealthResponse $response, bool $updating = false): array
    {
        $rules = [
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'details' => [$updating ? 'required' : 'sometimes', 'array'],
        ];

        foreach (['drfo', 'dracs_death', 'mvs_passport', 'dms_passport', 'dracs_name_change'] as $stream) {
            $key = 'details.' . $stream;
            $rules[$key] = ['sometimes', 'array'];
            $rules[$key . '.verification_status'] = ['required_with:' . $key, Rule::enum(VerificationStatus::class)];
            $rules[$key . '.verification_reason'] = ['required_with:' . $key, 'string'];
        }
        $rules += [
            'details.drfo.result' => 'nullable|numeric',
            'details.dracs_death.verification_comment' => 'nullable|string',
            'details.mvs_passport.status' => 'nullable|numeric',
            'details.dms_passport.status' => 'nullable|numeric',
            'details.dracs_name_change.verification_comment' => 'nullable|string',
        ];

        return Validator::make($response->getData(), $rules)->validated();
    }

    /**
     * Validates the response for a list of party verification statuses.
     * UPDATED: Added rules for 'details'
     *
     * @param  EHealthResponse  $response  The response from the eHealth API.
     * @return array The extracted 'data' array from the response.
     * @throws ValidationException
     */
    protected function validateMany(EHealthResponse $response): array
    {
        $fullResponse = $response->json();

        $rules = [
            'data' => 'present|array',
            'data.*.party_id' => 'required|uuid',
            'data.*.verification_status' => 'required|string',

            'data.*.details' => 'nullable|array',
            'data.*.details.drfo' => 'nullable|array',
            'data.*.details.drfo.verification_status' => 'nullable|string',
            'data.*.details.dracs_death' => 'nullable|array',
            'data.*.details.dracs_death.verification_status' => 'nullable|string',
            'data.*.details.mvs_passport' => 'nullable|array',
            'data.*.details.mvs_passport.verification_status' => 'nullable|string',
            'data.*.details.dms_passport' => 'nullable|array',
            'data.*.details.dms_passport.verification_status' => 'nullable|string',
            'data.*.details.dracs_name_change' => 'nullable|array',
            'data.*.details.dracs_name_change.verification_status' => 'nullable|string',
        ];

        $validator = Validator::make($fullResponse, $rules);

        try {
            $validatedData = $validator->validated();

            return $validatedData['data'];

        } catch (ValidationException $e) {
            Log::error('eHealth party list validation failed.', [
                'errors' => $e->errors(),
                'data_received' => $fullResponse
            ]);
            throw $e;
        }
    }
}
