<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

use App\Livewire\Referral\Forms\EReferralForm;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Editable local draft attributes; references and quantities remain owned by the persisted model. */
#[Map(source: EReferralForm::class)]
class Model
{
    #[Map(source: 'note')]
    public ?string $note = null;

    #[Map(source: 'patientInstruction')]
    public ?string $patientInstruction = null;

    #[Map(source: 'priority')]
    public string $priority;

    public function applyTo(ServiceRequestRequest $request): void
    {
        $request->note = $this->note;
        $request->patientInstruction = $this->patientInstruction;
    }
}
