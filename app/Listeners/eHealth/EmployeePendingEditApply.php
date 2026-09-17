<?php

declare(strict_types=1);

namespace App\Listeners\eHealth;

use App\Events\EHealthUserLogin;
use App\Models\Employee\EmployeeRequest;
use App\Services\Employee\EmployeeRequestProcessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * After #802/#803, EmployeeCreate skips pending edits on login (no EmployeeRequest API there).
 * Roles with employee_request:read still need a safe apply path after email confirmation:
 * Get Employee Request by ID via syncSinglePendingRequest for this user's pending edits only.
 *
 * Runs after EmployeeCreate. Does nothing without the scope (avoids 403 for receptionist/med_admin).
 */
class EmployeePendingEditApply
{
    public function __construct(private EmployeeRequestProcessor $processor)
    {
    }

    public function handle(EHealthUserLogin $event): void
    {
        setPermissionsTeamId($event->legalEntity->id);
        Auth::shouldUse($event->guard);

        $user = $event->user->loadMissing('roles', 'permissions');

        if (!$user->can('employee_request:read')) {
            Log::info('[EmployeePendingEditApply] Skip: user lacks employee_request:read.', [
                'user_id' => $user->id,
                'legal_entity_id' => $event->legalEntity->id,
            ]);

            return;
        }

        $pendingEdits = EmployeeRequest::query()
            ->with(['revision', 'employee', 'party', 'division'])
            ->where('email', $user->email)
            ->pendingEhealth()
            ->whereNotNull('employee_id')
            ->whereNotNull('uuid')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            // One sync per employee: the newest pending edit only (older supersedes are ignored here).
            ->unique(fn (EmployeeRequest $request): int => (int) $request->employeeId)
            ->values();

        if ($pendingEdits->isEmpty()) {
            return;
        }

        Log::info('[EmployeePendingEditApply] Syncing latest pending edits after login.', [
            'user_id' => $user->id,
            'request_ids' => $pendingEdits->pluck('id')->all(),
        ]);

        foreach ($pendingEdits as $request) {
            try {
                $result = $this->processor->syncSinglePendingRequest($request, $event->legalEntity);

                Log::info('[EmployeePendingEditApply] Sync outcome.', [
                    'request_id' => $request->id,
                    'outcome' => $result['outcome'],
                ]);
            } catch (Throwable $e) {
                // Do not break login if one request fails; remaining edits can retry on next login/sync.
                Log::error('[EmployeePendingEditApply] Sync failed for request.', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
