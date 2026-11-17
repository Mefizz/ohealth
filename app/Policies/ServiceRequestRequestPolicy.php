<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobStatus;
use App\Enums\Person\ServiceRequestStatus;
use App\Enums\Status;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServiceRequestRequestPolicy
{
    public function view(User $user, ServiceRequestRequest $request): Response
    {
        if ($this->search($user)->denied()
            || $user->cannot('view', Person::class)
            || legalEntity() === null
            || $request->person === null) {
            return Response::denyWithStatus(404);
        }

        $isReadable = ServiceRequestRequest::readableBy($user, $request->person, legalEntity())
            ->whereKey($request->id)->exists();

        return $isReadable ? Response::allow() : Response::denyWithStatus(404);
    }

    /** Only the active author may change a local draft that has not entered asynchronous signing. */
    public function edit(User $user, ServiceRequestRequest $request): Response
    {
        if ($this->view($user, $request)->denied()
            || $user->cannot('service_request:write')
            || $request->status !== ServiceRequestStatus::DRAFT->value
            || $request->detailsSyncedAt === null
            || $request->ehealthLinks()->whereStatus(JobStatus::PENDING->value)->exists()) {
            return Response::denyWithStatus(404);
        }

        $author = $request->employee;
        $isAuthor = $author !== null
            && $user->partyId !== null
            && (int) $author->partyId === (int) $user->partyId
            && (int) $author->legalEntityId === (int) legalEntity()->id
            && $author->isActive
            && $author->status === Status::APPROVED;

        return $isAuthor ? Response::allow() : Response::denyWithStatus(404);
    }

    public function sign(User $user, ServiceRequestRequest $request): Response
    {
        return $this->edit($user, $request);
    }

    public function recall(User $user, ServiceRequestRequest $request): Response
    {
        return $this->changeActiveStatus($user, $request, 'service_request:recall');
    }

    public function cancel(User $user, ServiceRequestRequest $request): Response
    {
        return $this->changeActiveStatus($user, $request, 'service_request:cancel');
    }

    public function resendSms(User $user, ServiceRequestRequest $request): Response
    {
        return $this->changeActiveStatus($user, $request, 'service_request:use');
    }

    protected function changeActiveStatus(User $user, ServiceRequestRequest $request, string $scope): Response
    {
        $allowed = $this->view($user, $request)->allowed()
            && $user->can($scope)
            && $request->status === ServiceRequestStatus::ACTIVE->value
            && !$request->ehealthLinks()->whereStatus(JobStatus::PENDING->value)->exists();

        return $allowed ? Response::allow() : Response::denyWithStatus(404);
    }

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

    public function complete(User $user): Response
    {
        return $user->can('service_request:complete') ? Response::allow() : Response::denyWithStatus(404);
    }
}
