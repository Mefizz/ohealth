<?php

declare(strict_types=1);

namespace App\Livewire\Referral;

use App\Dto\ServiceRequest\Form as ReferralFormData;
use App\Dto\ServiceRequest\Model as ReferralData;
use App\Livewire\Referral\Forms\EReferralForm;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EReferralEdit extends Component
{
    public EReferralForm $eReferralForm;

    #[Locked]
    public int $personId;

    #[Locked]
    public int $referralId;

    #[Locked]
    public int $legalEntityId;

    #[Locked]
    public string $patientFullName;

    #[Locked]
    public string $draftVersion;

    public function mount(LegalEntity $legalEntity, Person $person, ServiceRequestRequest $serviceRequest): void
    {
        abort_unless($serviceRequest->personId === $person->id, 404);
        $this->personId = $person->id;
        $this->referralId = $serviceRequest->id;
        $this->legalEntityId = $legalEntity->id;
        $this->patientFullName = $person->fullName;
        $this->draftVersion = $this->editableVersion($serviceRequest);
        $this->eReferralForm->setReferral(objectMapper()->map($serviceRequest, ReferralFormData::class));
    }

    public function save(): void
    {
        $legalEntity = legalEntity();
        $record = Repository::serviceRequest()->findForPerson($this->referralId, $this->personId);
        if ($legalEntity?->id !== $this->legalEntityId || Auth::user()->cannot('edit', [$record, $legalEntity])) {
            session()->flash('error', __('referrals.messages.action_unavailable'));

            return;
        }

        $this->eReferralForm->validate();
        $data = objectMapper()->map($this->eReferralForm, ReferralData::class);

        try {
            $saved = DB::transaction(function () use ($data, $legalEntity): ?ServiceRequestRequest {
                // Signing and editing lock the same draft. A second editor must reopen a changed draft.
                $record = Repository::serviceRequest()->findForPerson($this->referralId, $this->personId, lockForUpdate: true);
                if (Auth::user()->cannot('edit', [$record, $legalEntity]) || $this->editableVersion($record) !== $this->draftVersion) {
                    return null;
                }

                return Repository::serviceRequest()->saveMapped($data, $record);
            });
        } catch (QueryException $exception) {
            Log::channel('db_errors')->error('Unable to save the referral draft', ['sql_state' => $exception->getCode()]);
            session()->flash('error', __('messages.database_error'));

            return;
        }

        if ($saved === null) {
            session()->flash('error', __('referrals.messages.action_unavailable'));

            return;
        }

        $this->draftVersion = $this->editableVersion($saved);
        session()->flash('success', __('referrals.draft_saved'));
    }

    protected function editableVersion(ServiceRequestRequest $record): string
    {
        return hash('sha256', serialize($record->only(['note', 'patientInstruction', 'priorityId'])));
    }

    public function render(): View
    {
        return view('livewire.referral.e-referral-edit');
    }
}
