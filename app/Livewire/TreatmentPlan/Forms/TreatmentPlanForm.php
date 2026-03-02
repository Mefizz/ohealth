<?php

declare(strict_types=1);

namespace App\Livewire\TreatmentPlan\Forms;

use App\Models\TreatmentPlan\TreatmentPlan;
use Livewire\Form;
use Livewire\Component;
use App\Rules\DateFormat;
use Carbon\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TreatmentPlanForm extends Form
{
    public string $category = '';
    public string $nameTreatmentPlan = '';
    public string $intention = '';
    public string $termsService = '';
    public ?string $encounter = null; // Reference to encounter UUID
    
    // We'll flatten the period array or keep it as UI requires.
    // The UI uses x-model="period.during.startDate"
    // We can define it as an array to map easily to the blade structure
    public array $period = [
        'during' => [
            'startDate' => '',
            'startTime' => '',
            'endDate' => '',
            'endTime' => '',
        ]
    ];

    public ?string $knedp = null;
    public ?TemporaryUploadedFile $keyContainerUpload = null;
    public ?string $password = null;

    /**
     * Define the validation rules dynamically for the component.
     */
    public function rulesForSave(Component $component): array
    {
        return [
            'category' => ['required', 'string'],
            'nameTreatmentPlan' => ['required', 'string', 'max:255'],
            'intention' => ['required', 'string'],
            'termsService' => ['required', 'string'],
            'encounter' => ['required', 'uuid'],
            'period.during.startDate' => ['required', new DateFormat()],
            'period.during.startTime' => ['nullable', 'date_format:H:i'],
            'period.during.endDate' => [
                'nullable',
                new DateFormat(),
                'after_or_equal:period.during.startDate'
            ],
            'period.during.endTime' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function rulesForKepOnly(): array
    {
        return [
            'knedp' => ['required', 'string'],
            'password' => ['required', 'string'],
            'keyContainerUpload' => ['required', 'file', 'extensions:dat,pfx,pk8,zs2,jks,p7s'],
        ];
    }

    /**
     * Map UI validation attribute names.
     */
    public function validationAttributes(): array
    {
        return [
            'category' => __('treatment-plan.category'),
            'nameTreatmentPlan' => __('treatment-plan.name_treatment_plan'),
            'intention' => __('treatment-plan.intention'),
            'termsService' => __('treatment-plan.terms_service'),
            'encounter' => __('Взаємодія'),
            'period.during.startDate' => __('treatment-plan.date_and_time_start'),
            'period.during.endDate' => __('treatment-plan.date_and_time_end'),
        ];
    }

    /**
     * Prepares and returns data for repository / database storing.
     */
    public function getPreparedData(): array
    {
        $formData = $this->all();

        $startDateTime = null;
        if (!empty($formData['period']['during']['startDate'])) {
            $startTime = $formData['period']['during']['startTime'] ?: '00:00';
            $startDateTime = Carbon::createFromFormat(
                'd.m.Y H:i',
                $formData['period']['during']['startDate'] . ' ' . $startTime
            )->format('Y-m-d H:i:s');
        }

        $endDateTime = null;
        if (!empty($formData['period']['during']['endDate'])) {
            $endTime = $formData['period']['during']['endTime'] ?: '23:59';
            $endDateTime = Carbon::createFromFormat(
                'd.m.Y H:i',
                $formData['period']['during']['endDate'] . ' ' . $endTime
            )->format('Y-m-d H:i:s');
        }

        return [
            'category' => $formData['category'],
            'name_treatment_plan' => $formData['nameTreatmentPlan'],
            'intention' => $formData['intention'],
            'terms_service' => $formData['termsService'],
            'encounter' => $formData['encounter'],
            'period_start' => $startDateTime,
            'period_end' => $endDateTime,
        ];
    }
}
