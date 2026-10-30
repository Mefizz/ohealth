<?php

declare(strict_types=1);

namespace App\Livewire\Person\Records;

use App\Livewire\DeviceRequest\DeviceRequestIndex;
use App\Models\LegalEntity;
use App\Models\Person\Person;
use Illuminate\Support\Str;

class PatientDeviceRequestView extends DeviceRequestIndex
{
    public bool $showDeviceCatalog = false;

    public bool $showDispenses = false;

    public bool $showSmsModal = false;

    public bool $showStatusActionModal = false;

    public function mount(LegalEntity $legalEntity, ?Person $person = null, ?string $deviceRequest = null): void
    {
        abort_unless($person?->exists && $deviceRequest !== null && Str::isUuid($deviceRequest), 404);
        $this->legalEntityId = $legalEntity->id;
        $this->personId = $person->id;
        $this->patientContext = true;
        $this->showDetails($deviceRequest, false);
    }

    public function search(bool $resetPage = true): void
    {
        // Lifecycle actions refresh the displayed request without loading the registry.
        if ($this->selectedId !== null) {
            $this->showDetails($this->selectedId, false);
        }
    }

    public function loadDispenses(): void
    {
        if (!$this->canPerform('device_dispense:read')) {
            return;
        }

        parent::loadDispenses();
        $this->showDispenses = true;
    }

    public function openSmsModal(): void
    {
        if (!$this->canPerform('device_request:resend')) {
            return;
        }
        abort_unless($this->selectedId !== null && $this->phone !== '' && ($this->selected['status'] ?? '') === 'active', 404);
        $this->phoneConfirmed = false;
        $this->showSmsModal = true;
    }

    public function resendSms(): void
    {
        $phoneWasConfirmed = $this->phoneConfirmed;
        parent::resendSms();
        if ($phoneWasConfirmed && !$this->phoneConfirmed) {
            $this->showSmsModal = false;
        }
    }

    public function startAction(string $action): void
    {
        if (!$this->canPerform('device_request:'.$action)) {
            return;
        }

        $this->showStatusActionModal = false;
        $this->action = '';
        parent::startAction($action);
        $this->showStatusActionModal = $this->action === $action;
    }

    public function openSignatureModal(): void
    {
        parent::openSignatureModal();
        if ($this->showSignatureModal) {
            $this->showStatusActionModal = false;
        }
    }

    public function render()
    {
        return view('livewire.person.records.device-request-view', [
            'person' => $this->person,
            'parameterNames' => dictionary()->basics()->byName('device_request_code_parameter')->flattenedChildValues()->toArray(),
        ])->layout('layouts.app');
    }
}
