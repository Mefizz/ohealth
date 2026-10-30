<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ClearEmployeeSpecialities as ClearJob;
use App\Repositories\EmployeeRepository;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class ClearEmployeeSpecialities extends Command
{
    protected $signature = 'specialities_clear
        {--legal-entity= : Restrict to one local legal entity ID; default is the whole database}
        {--user= : Restrict to one logged-in operator; default is available eHealth sessions}
        {--employee= : Restrict to one Employee UUID}
        {--apply : Remove confirmed extra local specialities; default is queued audit only}';

    protected $description = 'One-off queued repair using the operator eHealth session; audit only unless --apply is supplied';

    public function handle(EmployeeRepository $repository, UserRepository $userRepository): int
    {
        $legalEntityId = $this->option('legal-entity') === null ? null : filter_var($this->option('legal-entity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $userId = $this->option('user') === null ? null : filter_var($this->option('user'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $employeeUuid = $this->option('employee');
        if ($legalEntityId === false || $userId === false || ($employeeUuid !== null && !Str::isUuid($employeeUuid))) {
            $this->error('Provide positive --legal-entity and --user IDs, and a valid optional --employee UUID.');

            return self::INVALID;
        }
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        if (in_array($driver, [null, 'sync', 'null'], true)) {
            $this->error('An asynchronous queue connection is required.');

            return self::INVALID;
        }

        $sessions = $userRepository->getSpecialityClearSessions($userId);
        $queued = 0;
        $skipped = [];
        foreach ($repository->specialityClearCandidates($legalEntityId, $employeeUuid)->chunk(100) as $employees) {
            $jobs = [];
            foreach ($employees as $employee) {
                $session = $sessions[$employee->legalEntity?->uuid ?? ''] ?? null;
                if ($session === null) {
                    $skipped[$employee->legalEntityId] = ($skipped[$employee->legalEntityId] ?? 0) + 1;
                    continue;
                }
                $jobs[] = new ClearJob($employee->id, $employee->legalEntityId, $session['user'], (bool) $this->option('apply'), $employee->uuid);
            }
            if ($jobs !== []) {
                $batch = Bus::batch($jobs)->name('EmployeeSpecialitiesClear')->allowFailures()->onQueue('sync')->dispatch();
                $queued += count($jobs);
                $this->line('Queued '.count($jobs).' employees; batch '.$batch->id.'.');
            }
        }
        foreach ($skipped as $id => $count) {
            $this->warn("Skipped {$count} employees in local legal entity {$id}: no unexpired eHealth session with employee:details for this entity.");
        }
        $this->info('Queued '.$queued.' employees. Mode: '.($this->option('apply') ? 'APPLY' : 'AUDIT (no deletions)').'.');
        $this->line('Run the sync queue worker; results are logged as EmployeeSpecialitiesClear. Retry failed jobs via queue:retry-batch BATCH_ID.');

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }
}
