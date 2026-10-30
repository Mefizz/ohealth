<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ClearEmployeeSpecialities as ClearJob;
use App\Repositories\EmployeeRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ClearEmployeeSpecialities extends Command
{
    protected $signature = 'specialities_clear
        {--legal-entity= : Local legal entity ID (required)}
        {--user= : Operator user ID for the shared eHealth rate limiter (required)}
        {--employee= : Restrict to one Employee UUID}
        {--verified-employees= : JSON file of reviewed MIS-created Employee UUIDs; required with --apply}
        {--apply : Remove confirmed extra local specialities; default is queued audit only}';

    protected $description = 'One-off queued repair of MIS-created employees; requires EHEALTH_ACCESS_TOKEN and an async queue';

    public function handle(EmployeeRepository $repository): int
    {
        $legalEntityId = filter_var($this->option('legal-entity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $userId = filter_var($this->option('user'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $employeeUuid = $this->option('employee');
        if (!$legalEntityId || !$userId || ($employeeUuid !== null && !Str::isUuid($employeeUuid))) {
            $this->error('Provide positive --legal-entity and --user IDs, and a valid optional --employee UUID.');

            return self::INVALID;
        }
        $verifiedUuids = [];
        $verifiedPath = $this->option('verified-employees');
        if ($verifiedPath !== null) {
            try {
                if (!is_file($verifiedPath) || !is_readable($verifiedPath)) {
                    throw new \RuntimeException('Unreadable verified UUID file.');
                }
                $verifiedUuids = json_decode(file_get_contents($verifiedPath), true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($verifiedUuids) || !array_is_list($verifiedUuids) || $verifiedUuids === []
                    || count(array_filter($verifiedUuids, fn ($uuid) => is_string($uuid) && Str::isUuid($uuid))) !== count($verifiedUuids)) {
                    throw new \RuntimeException('Invalid verified UUID list.');
                }
            } catch (\Throwable) {
                $this->error('--verified-employees must be a readable JSON array of reviewed Employee UUIDs.');

                return self::INVALID;
            }
        }
        if ($this->option('apply') && $verifiedUuids === []) {
            $this->error('--apply requires --verified-employees: old employee_id links alone do not prove creation by our MIS.');

            return self::INVALID;
        }
        [$legalEntity, $user] = $repository->specialityClearContext($legalEntityId, $userId);
        $token = getenv('EHEALTH_ACCESS_TOKEN');
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        if (!$legalEntity || !$user || !is_string($token) || trim($token) === '' || in_array($driver, [null, 'sync', 'null'], true)) {
            $this->error('Existing legal entity/operator, EHEALTH_ACCESS_TOKEN and an asynchronous queue connection are required.');

            return self::INVALID;
        }

        $encryptedToken = Crypt::encryptString($token);
        $jobs = [];
        foreach ($repository->specialityClearCandidates($legalEntityId, $employeeUuid) as $plan) {
            if ($this->option('apply') && !in_array($plan['employeeUuid'], $verifiedUuids, true)) {
                continue;
            }
            $jobs[] = new ClearJob($plan['requestId'], $legalEntityId, $user, $encryptedToken, (bool) $this->option('apply'), $this->option('apply') ? $plan['employeeUuid'] : null);
        }
        if ($jobs === []) {
            $this->info('No eligible creation-request candidates with extra local codes. Unproven, ambiguous, pending and unapproved UUIDs are excluded.');

            return self::SUCCESS;
        }
        $batch = Bus::batch($jobs)->name('EmployeeSpecialitiesClear')->allowFailures()->onQueue('sync')->dispatch();
        $this->info('Queued '.count($jobs).' candidates; batch '.$batch->id.'. Mode: '.($this->option('apply') ? 'APPLY' : 'AUDIT (no deletions)').'.');
        $this->line('Run the sync queue worker; results are logged as EmployeeSpecialitiesClear. Failed jobs can be retried via queue:retry-batch '.$batch->id.'.');

        return self::SUCCESS;
    }
}
