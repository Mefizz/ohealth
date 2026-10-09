<?php

declare(strict_types=1);

namespace App\Livewire\Employee;

use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

class EmployeeRequestShow extends EmployeeComponent
{
    protected EmployeeRequest $employee;

    #[Locked]
    public ?int $employeeRequestId = null;
    public bool $isPersonalDataLocked = true;
    public bool $isPositionDataLocked = true;
    public bool $isPartyDataPartiallyLocked = false;
    public ?Collection $partyUsers = null;

    public function mount(LegalEntity $legalEntity, EmployeeRequest $employee_request): void
    {
        abort_unless($employee_request->belongsToLegalEntity($legalEntity), 404);

        $this->loadDictionaries();
        $this->loadDivisions($legalEntity);
        $this->employee = $employee_request;
        $this->employeeRequestId = $employee_request->id;
        $this->form->hydrate($this->employee);
    }

    public function boot(): void
    {
        if ($this->employeeRequestId) {
            $this->employee = EmployeeRequest::forLegalEntity(legalEntity())->findOrFail($this->employeeRequestId);
        }
    }

    public function render(): View
    {
        $partyExistingPositions = null;
        if ($this->employee->party) {
            $partyExistingPositions = $this->employee->party->employees()->where('legal_entity_id', legalEntity()->id)->with('division')->get()
                ->merge($this->employee->party->employeeRequests()->forLegalEntity(legalEntity())->with('division')->get());
        }

        return view('livewire.employee.employee-show', [
            'employee' => $this->employee,
            'partyExistingPositions' => $partyExistingPositions
        ]);
    }
}
