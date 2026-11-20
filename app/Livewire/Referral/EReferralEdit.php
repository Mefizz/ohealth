<?php

declare(strict_types=1);

namespace App\Livewire\Referral;

use App\Dto\ServiceRequest\Model as ReferralData;
use App\Livewire\Referral\EReferralComponent;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

class EReferralEdit extends EReferralComponent
{
    #[Locked]
    public string $draftVersion = '';

    public function mount(LegalEntity $legalEntity, Person $person, ServiceRequestRequest $serviceRequest): void
    {
        $this->initializeReferral($legalEntity->id, $person->id, $serviceRequest->id);
        $this->draftVersion = $this->editableVersion($this->referral());
    }

    public function save(): void
    {
        $record = $this->referral();
        if (Auth::user()->cannot('edit', [$record, $this->legalEntity])) {
            session()->flash('error', __('referrals.messages.action_unavailable'));

            return;
        }

        $this->eReferralForm->validate();

        try {
            $data = $this->objectMapper->map($this->eReferralForm, ReferralData::class);

            $saved = DB::transaction(function () use ($data): ServiceRequestRequest|string {
                // Recheck under the same row lock as persistence: signing may have started after the form opened.
                $record = Repository::serviceRequest()->findForPerson($this->referralId, $this->personId, lockForUpdate: true);
                if (Auth::user()->cannot('edit', [$record, $this->legalEntity])) {
                    return __('referrals.messages.action_unavailable');
                }
                if ($this->editableVersion($record) !== $this->draftVersion) {
                    return __('referrals.messages.draft_changed');
                }

                return Repository::serviceRequest()->saveMapped($data, $record);
            });
        } catch (ValidationException $exception) {
            // Repository submission errors have no editable field on this page.
            session()->flash('error', __('referrals.messages.action_unavailable'));

            throw $exception;
        } catch (Throwable $exception) {
            Log::channel('db_errors')->error('Referral draft save failed', ['exception' => $exception::class]);
            session()->flash('error', __('messages.database_error'));

            return;
        }

        if (is_string($saved)) {
            session()->flash('error', $saved);

            return;
        }

        $this->draftVersion = $this->editableVersion($saved);
        $this->referralModel = null;

        // Local fields already equal the saved DTO; refill is reserved for remote sync or server normalization.
        session()->flash('success', __('referrals.draft_saved'));
    }

    protected function editableVersion(ServiceRequestRequest $record): string
    {
        // Compare the fields this form can overwrite, including edits made within the same second.
        return hash('sha256', serialize($record->only(['note', 'patientInstruction', 'priorityId'])));
    }

    public function render(): View
    {
        return view('livewire.referral.e-referral-edit', $this->referralViewData());
    }
}
