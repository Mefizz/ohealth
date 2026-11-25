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

    /** POST /api/patients/{patient_id}/care_plans. */
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

    /** GET /api/care_plans. */
    public function getMany(User $user): Response
    {
        return $this->viewAny($user);
    }

    /** GET /api/patients/{patient_id}/care_plans. */
    public function getBySearchParams(User $user): Response
    {
        return $this->viewAny($user);
    }

    /** GET /api/patients/{patient_id}/care_plans/{id}. ESOZ also checks patient-level access. */
    public function getDetails(User $user): Response
    {
        return $this->viewAny($user);
    }

    /** GET /api/patients/{patient_id}/summary/care_plans. */
    public function getSummary(User $user): Response
    {
        return $user->can('patient_summary:read') ? Response::allow() : Response::denyWithStatus(404);
    }

    /** PATCH the registered plan's cancel action. */
    public function cancel(User $user, ?CarePlan $carePlan = null): Response
    {
        return $carePlan === null ? $this->create($user) : $this->update($user, $carePlan);
    }

    /** PATCH the registered plan's complete action. */
    public function complete(User $user, ?CarePlan $carePlan = null): Response
    {
        return $carePlan === null ? $this->create($user) : $this->update($user, $carePlan);
    }

    protected function belongsToCurrentFacility(CarePlan $carePlan): bool
    {
        $facilityId = legalEntity()?->id;

        return $facilityId !== null && (int) $carePlan->legalEntityId === (int) $facilityId;
    }
}
