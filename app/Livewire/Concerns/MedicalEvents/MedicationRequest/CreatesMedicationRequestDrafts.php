<?php

declare(strict_types=1);

namespace App\Livewire\Concerns\MedicalEvents\MedicationRequest;

use App\Classes\eHealth\EHealth;
use App\Dto\MedicationRequest\MedicationRequestPayloads;
use App\Repositories\MedicalEvents\MedicationRequestRepository;
use Carbon\CarbonImmutable;

trait CreatesMedicationRequestDrafts
{
    protected function submitMedicationRequestDraft(array $dbData, array $uuids, ?string $carePlanUuid, int $personId): string
    {
        $mapper = app(MedicationRequestPayloads::class);

        if (!empty($dbData['medication_program_id'])) {
            EHealth::medicationRequest()->prequalifyAndValidate($mapper->prequalify($dbData, $uuids, CarbonImmutable::now(), $carePlanUuid));
        }

        $result = EHealth::medicationRequest()->createAndResolve(
            $mapper->create($dbData, $uuids, CarbonImmutable::now(), $carePlanUuid)
        );

        $dbData['request_number'] = $result->requestNumber();
        $dbData['uuid'] = $result->uuid($dbData['uuid']);
        $dbData['ehealth_payload'] = $result->document();

        app(MedicationRequestRepository::class)->store($dbData, $personId);

        return $dbData['uuid'];
    }
}
