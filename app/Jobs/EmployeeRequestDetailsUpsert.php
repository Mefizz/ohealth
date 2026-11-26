<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\EHealth\EHealthConnectionException;
use Throwable;
use App\Services\Employee\EmployeeRequestProcessor;
use App\Core\EHealthJob;
use App\Models\LegalEntity;
use App\Classes\eHealth\EHealth;
use Illuminate\Queue\SerializesModels;
use App\Classes\eHealth\EHealthResponse;
use App\Models\Employee\EmployeeRequest;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;

class EmployeeRequestDetailsUpsert extends EHealthJob
{
    use Dispatchable;
    use SerializesModels;

    public const string BATCH_NAME = 'EmployeeRequestDetailsSync';

    public const string SCOPE_REQUIRED = 'employee_request:read';

    public const string ENTITY = LegalEntity::ENTITY_EMPLOYEE_REQUEST;

    protected const int RATE_LIMIT_DELAY = 3;

    public function __construct(
        public EmployeeRequest $employeeRequest,
        public ?LegalEntity $legalEntity,
        protected ?EHealthJob $nextEntity = null,
        public bool $standalone = false,
    ) {
        parent::__construct(legalEntity: $legalEntity, nextEntity: $nextEntity, standalone: $standalone);
    }

    /**
     * Get data from EHealth API
     *
     * @throws EHealthConnectionException
     */
    protected function sendRequest(string $token): PromiseInterface|EHealthResponse|null
    {
        return EHealth::employeeRequest()->withToken($token)->getDetails($this->employeeRequest->uuid);
    }

    /**
     * Store or update data in the database
     *
     * @throws Throwable
     */
    protected function processResponse(?EHealthResponse $response): void
    {
        app(EmployeeRequestProcessor::class)->syncRequestDetails(
            $this->employeeRequest,
            $this->legalEntity,
            $response,
            $this->token,
        );
    }

    /**
     * Get additional middleware configurations for the job.
     *
     * @return array Returns an array of middleware configurations to be applied to the job
     */
    protected function getAdditionalMiddleware(): array
    {
        return [
            new RateLimited('ehealth-employee-request-get')
        ];
    }

    // Get next entity job if needed
    protected function getNextEntityJob(): ?EHealthJob
    {
        return $this->standalone || !$this->nextEntity
            ? new CompleteSync($this->legalEntity, isFirstLogin: $this->isFirstLogin)
            : $this->nextEntity;
    }
}
