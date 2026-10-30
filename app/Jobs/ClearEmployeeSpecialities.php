<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\eHealth\EHealth;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Models\User;
use App\Repositories\EmployeeRepository;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ClearEmployeeSpecialities implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 0;
    public int $maxExceptions = 3;
    public int $timeout = 60;
    private int $expiresAt;

    public function __construct(
        public int $requestId,
        public int $legalEntityId,
        public User $user,
        private string $encryptedToken,
        public bool $apply = false,
        private ?string $verifiedEmployeeUuid = null,
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
        $plan = $repository->refreshSpecialityClearPlan($this->requestId, $this->legalEntityId);
        if ($plan === null) {
            return;
        }
        if ($this->apply && $this->verifiedEmployeeUuid !== $plan['employeeUuid']) {
            throw new \LogicException('Applying repair requires an explicitly reviewed Employee UUID.');
        }
        try {
            $response = EHealth::employee()->withToken(Crypt::decryptString($this->encryptedToken))->getDetails($plan['employeeUuid']);
        } catch (EHealthResponseException $exception) {
            if ($exception->getCode() !== 429) {
                throw $exception;
            }
            $retryAfter = $exception->response->header('Retry-After');
            $this->release(is_numeric($retryAfter) ? max(1, (int) $retryAfter) : 60);

            return;
        }
        $result = $repository->clearExtraSpecialities($plan, $response->getData(), $this->apply, $this->verifiedEmployeeUuid);
        Log::info('EmployeeSpecialitiesClear', ['employeeId' => $plan['employeeId'], 'employeeUuid' => $plan['employeeUuid'], 'requestId' => $this->requestId, 'requestUuid' => $plan['requestUuid'], 'apply' => $this->apply] + $result);
    }
}
