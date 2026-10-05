<?php

declare(strict_types=1);

namespace App\Traits\MedicalEvents;

use App\Classes\eHealth\EHealth;
use App\Dto\ServiceRequest\EhealthComplete;
use App\Enums\MedicalEvents\ReferralCompletionResourceType;
use App\Enums\Person\ServiceRequestStatus;
use App\Repositories\MedicalEvents\Repository;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/** Shared by the Livewire and HTTP entry points; it has no component state. */
trait UpdatesReferralExecution
{
    protected function completeReferral(string $referralUuid, string $resourceUuid, string $resourceType = 'encounter'): array
    {
        $type = ReferralCompletionResourceType::tryFrom($resourceType)
            ?? throw new \InvalidArgumentException(__('care-plan.referral_complete_invalid_emz_type'));

        if ($resourceUuid === '') {
            throw new \InvalidArgumentException(__('care-plan.referral_complete_emz_required'));
        }

        Repository::serviceRequest()->assertCompletionResourceOwned($referralUuid, $resourceUuid, $type);
        $source = (object) ['basedOn' => [(object) ['type' => $type->value, 'uuid' => $resourceUuid]]];
        $payload = app(ObjectMapperInterface::class)->map($source, EhealthComplete::class)->toArray();
        $response = EHealth::serviceRequest()->completeAndResolve($referralUuid, $payload);
        Repository::serviceRequest()->setExecutionStatus($referralUuid, ServiceRequestStatus::COMPLETED);

        return $response;
    }

    protected function cancelReferralUsage(string $referralUuid, string $patientUuid, array $payload = []): array
    {
        $response = EHealth::serviceRequest()->cancelUsage($referralUuid, $patientUuid, $payload)->getData();
        Repository::serviceRequest()->setExecutionStatus($referralUuid, ServiceRequestStatus::ACTIVE);

        return $response;
    }
}
