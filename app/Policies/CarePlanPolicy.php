<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CarePlanStatus;
use App\Models\CarePlan;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\MedicalEvents\CarePlanApprovalService;
use Illuminate\Auth\Access\Response;

class CarePlanPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->search($user);
    }

    public function view(User $user, CarePlan $carePlan): Response
    {
        return $this->viewAny($user)->allowed() && $this->belongsToCurrentFacility($carePlan)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    public function updateAny(User $user, LegalEntity $legalEntity): Response
    {
        return $legalEntity->is(legalEntity()) && $this->viewAny($user)->allowed() && $user->can('care_plan:write')
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    public function update(User $user, CarePlan $carePlan): Response
    {
        $legalEntity = legalEntity();
        if ($legalEntity === null || $this->updateAny($user, $legalEntity)->denied()
            || $this->view($user, $carePlan)->denied()) {
            return Response::denyWithStatus(404);
        }

        return CarePlanStatus::fromStored($carePlan->status) === CarePlanStatus::DRAFT
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    /** Determine whether the user can search care plans in eHealth. */
    public function search(User $user): Response
    {
        return $user->can('care_plan:read') ? Response::allow() : Response::denyWithStatus(404);
    }

    public function create(User $user): Response
    {
        return $user->can('care_plan:write')
            ? Response::allow()
            : Response::deny(__('care-plan.no_permission_create'));
    }

    public function getSummary(User $user): Response
    {
        return $user->can('patient_summary:read') ? Response::allow() : Response::denyWithStatus(404);
    }

    public function cancel(User $user): Response
    {
        return $user->can('care_plan:write') ? Response::allow() : Response::denyWithStatus(404);
    }

    public function complete(User $user): Response
    {
        return $user->can('care_plan:write') ? Response::allow() : Response::denyWithStatus(404);
    }

    public function manage(User $user, CarePlan $carePlan): Response
    {
        return $user->can('care_plan:write') && $this->belongsToCurrentFacility($carePlan)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    /** TODO: Move prescribing callers to their action policies when separating activity access. */
    public function manageActivities(User $user, CarePlan $carePlan): Response
    {
        $permission = $this->manage($user, $carePlan);
        if ($permission->denied()) {
            return $permission;
        }

        return app(CarePlanApprovalService::class)->permitsActivityAccess($carePlan, $user)
            ? Response::allow()
            : Response::deny(__('care-plan.prescribing_patient_access_required'));
    }

    protected function belongsToCurrentFacility(CarePlan $carePlan): bool
    {
        $facilityId = legalEntity()?->id;

        return $facilityId !== null && (int) $carePlan->legalEntityId === (int) $facilityId;
    }
}
