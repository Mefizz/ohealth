<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\EHealthResponse;
use App\Core\EHealthJob;
use App\Models\LegalEntity;
use App\Enums\Employee\RequestStatus;
use App\Services\Employee\EmployeeRequestProcessor;
use App\Traits\BatchLegalEntityQueries;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

class EmployeeRequestsSyncAll extends EHealthJob
{
    use BatchLegalEntityQueries;

    public const string BATCH_NAME = 'EmployeeRequestsSyncAll';
    public const string SCOPE_REQUIRED = 'employee_request:read';
    public const string ENTITY = LegalEntity::ENTITY_EMPLOYEE_REQUEST;

    protected RequestStatus $requestStatus = RequestStatus::NEW;

    public function __construct(
        ?LegalEntity $legalEntity = null,
        ?EHealthJob $nextEntity = null,
        bool $isFirstLogin = false,
        int $page = 1,
        bool $standalone = false,
        RequestStatus $requestStatus = RequestStatus::NEW,
    ) {
        parent::__construct($legalEntity, $nextEntity, $isFirstLogin, $page, $standalone);
        $this->requestStatus = $requestStatus;
    }

    public function handle(): void
    {
        if ($this->batch()?->processedJobs === 0) {
            $this->sendEntityNotification(static::ENTITY, 'started');
        }

        parent::handle();
    }

    protected function sendRequest(string $token): PromiseInterface|EHealthResponse
    {
        Log::info('[EmployeeRequestsSyncAll] Sending request for page ' . $this->page);

        return EHealth::employeeRequest()
            ->withToken($token)
            ->getMany([
                'edrpou' => $this->legalEntity->edrpou,
                'status' => $this->requestStatus->value,
            ], $this->page);
    }

    protected function processResponse(?EHealthResponse $response): void
    {
        Log::info('[EmployeeRequestsSyncAll] Processing page ' . $this->page);

        $validatedData = $response->validate();

        // Use the service to process the data
        $processor = app(EmployeeRequestProcessor::class);
        $processor->processBatch($validatedData, $this->legalEntity);
    }

    protected function getAdditionalMiddleware(): array
    {
        return [new RateLimited('ehealth-employee-request-get')];
    }

    /**
     * Get the next entity job to be scheduled after EmployeeRequestSync completes.
     *
     * If the job is standalone, returns a CompleteSync job for the current legal entity.
     * Otherwise, returns a chain of EmployeeRequestDetailsUpsert jobs for employee requests with PARTIAL sync status.
     *
     * @return EHealthJob|null
     */
    protected function getNextEntityJob(): ?EHealthJob
    {
        // eHealth defaults to NEW. Each documented status must have its own complete pagination.
        $nextStatus = match ($this->requestStatus) {
            RequestStatus::NEW => RequestStatus::APPROVED,
            RequestStatus::APPROVED => RequestStatus::REJECTED,
            default => null,
        };
        if ($nextStatus !== null) {
            return new self(
                legalEntity: $this->legalEntity,
                nextEntity: $this->nextEntity,
                isFirstLogin: $this->isFirstLogin,
                standalone: $this->standalone,
                requestStatus: $nextStatus,
            );
        }

        $nextEntity = $this->getEmployeeRequestDetailsStartJob($this->legalEntity, $this->nextEntity) ?? $this->nextEntity;

        return $nextEntity ?? new CompleteSync($this->legalEntity, isFirstLogin: $this->isFirstLogin);
    }

    protected function getNextPageJob(): EHealthJob
    {
        return new self(
            legalEntity: $this->legalEntity,
            nextEntity: $this->nextEntity,
            isFirstLogin: $this->isFirstLogin,
            page: $this->page + 1,
            standalone: $this->standalone,
            requestStatus: $this->requestStatus,
        );
    }
}
