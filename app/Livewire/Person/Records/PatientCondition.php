<?php

declare(strict_types=1);

namespace App\Livewire\Person\Records;

use App\Livewire\Person\Records\BasePatientComponent;
use Livewire\Attributes\Url;

class PatientCondition extends BasePatientComponent
{
    public string $filterCode = '';

    public string $filterEcozId = '';

    public string $filterMedicalRecordId = '';

    public string $filterCreatedAtRange = '';

    public string $filterClinicalStatus = '';

    public string $filterSeverity = '';

    public string $filterStartedAtRange = '';

    public string $filterVerificationStatus = '';

    public string $filterBodyPart = '';

    public string $filterPerformer = '';

    public string $filterSource = '';

    public bool $showAdditionalParams = false;

    public function render()
    {
        return view('livewire.person.records.condition');
    }

    public function search(): void
    {

    }

    public function syncConditions(): void
    {

    }

    public function resetFilters(): void
    {
        $this->reset([
            'filterCode',
            'filterEcozId',
            'filterMedicalRecordId',
            'filterCreatedAtRange',
            'filterClinicalStatus',
            'filterSeverity',
            'filterStartedAtRange',
            'filterVerificationStatus',
            'filterBodyPart',
            'filterPerformer',
            'filterSource',
        ]);
    }
}
