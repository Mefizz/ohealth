<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CarePlanStatus;
use App\Models\CarePlan;
use App\Models\User;
use App\Services\MedicalEvents\CarePlanApprovalService;
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

    /** Authorize changes to a draft in the current facility. */
    public function update(User $user, CarePlan $carePlan): Response
    {
        return $this->view($user, $carePlan)->allowed()
            && $this->manage($user, $carePlan)->allowed()
            && CarePlanStatus::fromStored($carePlan->status) === CarePlanStatus::DRAFT
                ? Response::allow()
                : Response::denyWithStatus(404);
    }

    public function getMany(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function getBySearchParams(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function getDetails(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function getSummary(User $user): Response
    {
        return $user->can('patient_summary:read') ? Response::allow() : Response::denyWithStatus(404);
    }

    public function cancel(User $user): Response
    {
        return $this->create($user);
    }

    public function complete(User $user): Response
    {
        return $this->create($user);
    }

    public function sync(User $user): Response
    {
        return $this->viewAny($user)->allowed() && $user->activeEmployee() !== null
            ? Response::allow()
            : Response::deny(__('care-plan.sync_access_denied'));
    }

    public function manage(User $user, CarePlan $carePlan): Response
    {
        return $user->can('care_plan:write') && $this->belongsToCurrentFacility($carePlan)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    public function manageActivities(User $user, CarePlan $carePlan): Response
    {
        $access = $this->manage($user, $carePlan);
        if ($access->denied()) {
            return $access;
        }

        $employee = $user->getCarePlanWriterEmployee($carePlan->termsOfServiceCode());
        $status = CarePlanStatus::fromStored($carePlan->status);
        if ($employee !== null && $status !== CarePlanStatus::DRAFT && !$status->isTerminal()
            && (app(CarePlanApprovalService::class)->skipsPatientOtp($carePlan)
                || $carePlan->hasGrantedApprovalForEmployeeUuid($employee->uuid))) {
            return Response::allow();
        }

        return Response::deny(__('care-plan.prescribing_patient_access_required'));
    }

    protected function belongsToCurrentFacility(CarePlan $carePlan): bool
    {
        $facilityId = legalEntity()?->id;

        return $facilityId !== null && (int) $carePlan->legalEntityId === (int) $facilityId;
    }
}
