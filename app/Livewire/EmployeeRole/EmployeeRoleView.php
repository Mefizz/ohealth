<?php

declare(strict_types=1);

namespace App\Livewire\EmployeeRole;

use App\Models\EmployeeRole;
use App\Models\LegalEntity;
use App\Traits\FormTrait;
use Illuminate\View\View;
use Livewire\Component;

class EmployeeRoleView extends Component
{
    use FormTrait;

    protected EmployeeRole $employeeRole;

    protected $dictionaryNames = ['SPECIALITY_TYPE'];

    public function mount(LegalEntity $legalEntity, EmployeeRole $employeeRole): void
    {
        $this->getDictionary();

        $this->employeeRole = $employeeRole->load([
            'employee.party',
            'healthcareService.division',
            'insertedByUser.party',
            'updatedByUser.party'
        ]);
    }

    public function render(): View
    {
        return view('livewire.employee-role.employee-role-view')->with(['employeeRole' => $this->employeeRole]);
    }
}
