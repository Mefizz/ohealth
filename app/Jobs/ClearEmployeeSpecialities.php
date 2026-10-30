<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\eHealth\EHealth;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Models\User;
use App\Repositories\EmployeeRepository;
use App\Repositories\Repository;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClearEmployeeSpecialities implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 0;
    public int $maxExceptions = 3;
    public int $timeout = 60;
    private int $expiresAt;

    public function __construct(
        public int $employeeId,
        public int $legalEntityId,
        public User $user,
        public bool $apply,
        private string $employeeUuid,
    ) {
        $this->expiresAt = now()->addDay()->timestamp;
        $this->onQueue('sync');
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled(), new RateLimited('ehealth-employee-get')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return Carbon::createFromTimestamp($this->expiresAt);
    }

    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function handle(EmployeeRepository $repository): void
    {
        $plan = $repository->refreshSpecialityClearPlan($this->employeeId, $this->legalEntityId, $this->employeeUuid);
        if ($plan === null) {
            Log::info('EmployeeSpecialitiesClear', ['employeeId' => $this->employeeId, 'employeeUuid' => $this->employeeUuid, 'legalEntityId' => $this->legalEntityId, 'apply' => $this->apply, 'outcome' => 'skipped_local_state']);

            return;
        }
        $session = Repository::user()->getSpecialityClearSessions($this->user->id)[$plan['legalEntityUuid']] ?? null;
        if ($session === null) {
            Log::info('EmployeeSpecialitiesClear', ['employeeId' => $this->employeeId, 'employeeUuid' => $this->employeeUuid, 'legalEntityId' => $this->legalEntityId, 'apply' => $this->apply, 'outcome' => 'awaiting_session']);
            $this->release(60);

            return;
        }
        try {
            $response = EHealth::employee()->withToken($session['token'])->getDetails($plan['employeeUuid']);
        } catch (EHealthResponseException $exception) {
            if ($exception->getCode() !== 429) {
                throw $exception;
            }
            $retryAfter = $exception->response->header('Retry-After');
            $this->release(is_numeric($retryAfter) ? max(1, (int) $retryAfter) : 60);

            return;
        }
        $result = $repository->clearExtraSpecialities($plan, $response->getData(), $this->apply);
        Log::info('EmployeeSpecialitiesClear', ['employeeId' => $plan['employeeId'], 'employeeUuid' => $plan['employeeUuid'], 'legalEntityId' => $plan['legalEntityId'], 'apply' => $this->apply] + $result);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('EmployeeSpecialitiesClear', ['employeeId' => $this->employeeId, 'employeeUuid' => $this->employeeUuid,
            'legalEntityId' => $this->legalEntityId, 'apply' => $this->apply, 'batchId' => $this->batchId,
            'outcome' => 'failed', 'exceptionType' => $exception ? $exception::class : null]);
    }
}
