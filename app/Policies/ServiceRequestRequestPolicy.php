<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobStatus;
use App\Enums\Person\ServiceRequestStatus;
use App\Enums\Status;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServiceRequestRequestPolicy
{
    /**
     * Determine whether the user can search electronic referrals.
     */
    public function search(User $user): Response
    {
        if ($user->cannot('service_request:read')) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }

    public function viewAny(User $user): Response
    {
        return $this->search($user)->allowed() && $user->can('view', Person::class)
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    public function view(User $user, ServiceRequestRequest $request, Person $patient, LegalEntity $legalEntity): Response
    {
        if ($request->personId !== $patient->id || !$legalEntity->is(legalEntity()) || $this->viewAny($user)->denied()) {
            return Response::denyWithStatus(404);
        }

        return ServiceRequestRequest::readableBy($user, $patient, $legalEntity)->whereKey($request->id)->exists()
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    /**
     * Determine whether the user can take an electronic referral into work.
     */
    public function use(User $user): Response
    {
        if ($user->cannot('service_request:use')) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }

    public function edit(User $user, ServiceRequestRequest $request, LegalEntity $legalEntity): Response
    {
        if (!in_array(ServiceRequestStatus::resolve($request->status), [ServiceRequestStatus::DRAFT, ServiceRequestStatus::NEW], true)
            || filled($request->requestNumber)
            || $request->detailsSyncedAt === null
            || !$legalEntity->is(legalEntity())
            || $user->cannot('view', Person::class)
            || $this->search($user)->denied()
            || $user->cannot('service_request:write')) {
            return Response::denyWithStatus(404);
        }

        $author = $request->employee;
        if ($author === null || !$author->isActive || $author->status !== Status::APPROVED
            || $user->partyId === null || $author->partyId !== $user->partyId
            || $author->legalEntityId !== $legalEntity->id) {
            return Response::denyWithStatus(404);
        }

        // The patient registry preloads this flag. A freshly locked draft checks the live submission.
        $pending = $request->submissionPending ?? $request->ehealthLinks()->whereStatus(JobStatus::PENDING->value)->exists();

        return $pending ? Response::denyWithStatus(404) : Response::allow();
    }

    public function complete(User $user): Response
    {
        return $user->can('service_request:complete') ? Response::allow() : Response::denyWithStatus(404);
    }
}
