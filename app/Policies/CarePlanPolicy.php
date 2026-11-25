<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CarePlanStatus;
use App\Enums\Episode\Status as EpisodeStatus;
use App\Enums\Person\EncounterStatus;
use App\Models\CarePlan;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\Episode;
use App\Models\Person\Person;
use App\Models\User;
use App\Repositories\Repository;
use App\Services\MedicalEvents\CarePlanApprovalService;
use Illuminate\Auth\Access\Response;

class CarePlanPolicy
{
    public function sync(User $user): Response
    {
        return $user->can('care_plan:read') && $user->activeEmployee() !== null
            ? Response::allow()
            : Response::deny(__('care-plan.sync_access_denied'));
    }

    /**
     * Determine whether the user can view the care plan.
     */
    public function view(User $user, CarePlan $carePlan): Response
    {
        if ($user->cannot('care_plan:read')) {
            return Response::denyWithStatus(404);
        }

        if ((int) $carePlan->legalEntityId !== (int) legalEntity()?->id) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }

    /**
     * Sign, cancel, complete, or issue requests on an already stored plan.
     * Distinct from update(): eHealth keeps a signed plan in `new` until the first activity.
     */
    public function manage(User $user, CarePlan $carePlan): Response
    {
        if ($user->cannot('care_plan:write')) {
            return Response::denyWithStatus(404);
        }

        if ((int) $carePlan->legalEntityId !== (int) legalEntity()?->id) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }

    /** A plan's registration does not itself grant patient access for prescribing. */
    public function manageActivities(User $user, CarePlan $carePlan): Response
    {
        $permission = $this->manage($user, $carePlan);
        if ($permission->denied()) {
            return $permission;
        }
        $legalEntity = legalEntity();
        $employee = $user->getCarePlanWriterEmployee($carePlan->termsOfServiceCode());
        $status = CarePlanStatus::fromStored($carePlan->status);
        if ($employee === null || $legalEntity === null || $status === CarePlanStatus::DRAFT || $status->isTerminal()) {
            return Response::deny(__('care-plan.prescribing_patient_access_required'));
        }
        $repository = Repository::carePlan();
        if (app(CarePlanApprovalService::class)->skipsPatientOtp($carePlan, $legalEntity)
            || $repository->hasGrantedApproval($carePlan, $employee->uuid)) {
            return Response::allow();
        }

        return Response::deny(__('care-plan.prescribing_patient_access_required'));
    }

    /**
     * Determine whether the user can create care plan.
     */
    public function create(User $user): Response
    {
        if ($user->cannot('care_plan:write')) {
            return Response::deny(__('care-plan.no_permission_create'));
        }

        return Response::allow();
    }

    /** Permit saving only in the user and facility context in which the draft form was opened. */
    public function saveDraft(User $user, ?int $openedById, ?int $legalEntityId): Response
    {
        if ($openedById !== $user->id || $legalEntityId === null || $legalEntityId !== legalEntity()?->id) {
            return Response::deny(__('care-plan.no_permission_create'));
        }

        return $this->create($user);
    }

    /** A local draft can use a finished, readable encounter in an episode managed by the current facility. */
    public function useEncounterForDraft(User $user, Encounter $encounter, Person $person): Response
    {
        if ($this->create($user)->denied() || legalEntity() === null
            || $encounter->status !== EncounterStatus::FINISHED
            || $user->cannot('view', [$encounter, $person])) {
            return Response::denyWithStatus(404);
        }

        $episodeIsEligible = $person->filterRecordsWithMerged(Episode::query())
            ->whereUuid($encounter->episode?->value)
            ->whereIn('status', [EpisodeStatus::ACTIVE, EpisodeStatus::CLOSED])
            ->managedByLegalEntity()
            ->exists();

        return $episodeIsEligible ? Response::allow() : Response::denyWithStatus(404);
    }

    /**
     * Determine whether the user can update the care plan.
     */
    public function update(User $user, CarePlan $carePlan): Response
    {
        if ($user->cannot('care_plan:write')) {
            return Response::denyWithStatus(404);
        }

        if ((int) $carePlan->legalEntityId !== (int) legalEntity()?->id) {
            return Response::denyWithStatus(404);
        }

        if (Repository::carePlan()->hasPendingSubmission($carePlan)) {
            return Response::denyWithStatus(404);
        }

        $status = CarePlanStatus::fromStored($carePlan->status);
        if ($status !== CarePlanStatus::DRAFT) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }
}
