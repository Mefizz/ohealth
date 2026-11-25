<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CarePlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CarePlanPolicy
{
    /** Read CarePlan lists and search results. */
    public function viewAny(User $user): Response
    {
        return $user->can('care_plan:read') ? Response::allow() : Response::denyWithStatus(404);
    }

    /** Read details of a plan owned by the current facility. */
    public function view(User $user, CarePlan $carePlan): Response
    {
        return $this->viewAny($user)->allowed() && $this->belongsToCurrentFacility($carePlan)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    /** Create a treatment plan. */
    public function create(User $user): Response
    {
        return $user->can('care_plan:write')
            ? Response::allow()
            : Response::deny(__('care-plan.no_permission_create'));
    }

    /** Authorize write access to an existing plan in the current facility. */
    public function update(User $user, CarePlan $carePlan): Response
    {
        return $user->can('care_plan:write') && $this->belongsToCurrentFacility($carePlan)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    protected function belongsToCurrentFacility(CarePlan $carePlan): bool
    {
        $facilityId = legalEntity()?->id;

        return $facilityId !== null && (int) $carePlan->legalEntityId === (int) $facilityId;
    }
}
