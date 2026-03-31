<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;

class DiagnosticReport extends Request
{
    protected const string URL = '/api/patients';

    /**
     * Create the diagnostic report for patient.
     *
     * @param  string  $uuid  Person UUID
     * @param  array  $data
     * @return EHealthResponse|PromiseInterface
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/diagnostic-report-data-package/submit-diagnostic-report-package
     */
    public function create(string $uuid, array $data = []): PromiseInterface|EHealthResponse
    {
        return $this->post(self::URL . "/$uuid/diagnostic_report_package", $data);
    }

    /**
     * Get a diagnostic report by ID.
     *
     * @param  string  $patientId
     * @param  string  $diagnosticReportId
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/diagnostic-report/get-diagnostic-report-by-id
     */
    public function getById(string $patientId, string $diagnosticReportId): PromiseInterface|EHealthResponse
    {
        return $this->get(self::URL . "/$patientId/diagnostic_reports/$diagnosticReportId");
    }

    /**
     * Get a list of info filtered by search params.
     *
     * @param  string  $patientId
     * @param  array{
     *     code?: string,
     *     encounter_id?: string,
     *     context_episode_id?: string,
     *     origin_episode_id?: string,
     *     issued_from?: string,
     *     issued_to?: string,
     *     based_on?: string,
     *     managing_organization_id?: string,
     *     specimen_id?: string,
     *     page?: int,
     *     page_size?: int
     *     }  $query
     * @return PromiseInterface|EHealthResponse
     * @throws ConnectionException|EHealthValidationException|EHealthResponseException
     *
     * @see https://medicaleventsmisapi.docs.apiary.io/#reference/medical-events/diagnostic-report/get-diagnostic-report-by-search-params
     */
    public function getBySearchParams(string $patientId, array $query = []): PromiseInterface|EHealthResponse
    {
        $this->setDefaultPageSize();

        $mergedQuery = array_merge($this->options['query'], $query ?? []);

        return $this->get(self::URL . "/$patientId/diagnostic_reports", $mergedQuery);
    }
}
