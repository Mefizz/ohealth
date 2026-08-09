<?php

declare(strict_types=1);

namespace App\Livewire\Encounter\Concerns;

use App\Classes\eHealth\EHealth;
use App\Enums\Person\EncounterStatus;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\Medications\MedicationRequestRequest;
use App\Models\Person\Person;
use App\Services\MedicalEvents\MedicationRequestLifecycleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

trait ManagesEncounterEPrescription
{
    // Uses ResolvesEncounterStandaloneContext via EncounterEdit.

    public bool $showEncounterEPrescriptionDrawer = false;

    /** @var array<string, mixed> */
    public array $encounterEPrescriptionForm = [];

    /** @var list<array{uuid: string, type: string, label: string}> */
    public array $encounterEPrescriptionAuthMethods = [];

    public ?string $encounterEPrescriptionRequestIdToSign = null;

    public string $encounterEPrescriptionWarningMessage = '';

    public function openEncounterEPrescriptionDrawer(): void
    {
        $encounter = $this->resolveEncounterModelForStandalone();
        if ($encounter === null) {
            return;
        }

        $status = $encounter->status instanceof EncounterStatus
            ? $encounter->status
            : EncounterStatus::tryFrom((string) $encounter->status);

        if ($status !== EncounterStatus::FINISHED) {
            $this->dispatch('flashMessage', [
                'type' => 'error',
                'message' => 'Електронний рецепт без плану лікування можна створити лише після завершення взаємодії.',
            ]);

            return;
        }

        $this->loadEncounterEPrescriptionAuthMethods($encounter);

        $this->encounterEPrescriptionForm = [
            'medication_id' => '',
            'program_id' => '',
            'medication_qty' => '1',
            'medication_unit' => 'од.',
            'signature_text' => '',
            'patient_instruction' => '',
            'route' => 'oral',
            'max_dose_per_administration' => '1',
            'max_dose_per_period' => '1',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addDays(30)->toDateString(),
            'note' => '',
            'inform_with' => $this->encounterEPrescriptionAuthMethods[0]['uuid'] ?? '',
        ];

        $this->encounterEPrescriptionWarningMessage = '';
        $this->showEncounterEPrescriptionDrawer = true;
    }

    public function closeEncounterEPrescriptionDrawer(): void
    {
        $this->showEncounterEPrescriptionDrawer = false;
        $this->encounterEPrescriptionWarningMessage = '';
    }

    public function validateEncounterEPrescription(): void
    {
        $this->encounterEPrescriptionWarningMessage = '';

        $this->validate([
            'encounterEPrescriptionForm.medication_id' => 'required|string|uuid',
            'encounterEPrescriptionForm.medication_qty' => 'required|numeric|min:0.01',
            'encounterEPrescriptionForm.signature_text' => 'required|string|min:1',
            'encounterEPrescriptionForm.max_dose_per_administration' => 'required|numeric|min:0.01',
            'encounterEPrescriptionForm.max_dose_per_period' => 'required|numeric|min:0.01',
            'encounterEPrescriptionForm.started_at' => 'required|date',
            'encounterEPrescriptionForm.ended_at' => 'required|date|after_or_equal:encounterEPrescriptionForm.started_at',
            'encounterEPrescriptionForm.inform_with' => 'required|string',
        ], [], [
            'encounterEPrescriptionForm.medication_id' => 'ідентифікатор ЛЗ',
            'encounterEPrescriptionForm.medication_qty' => 'кількість',
            'encounterEPrescriptionForm.signature_text' => 'сигнатура',
            'encounterEPrescriptionForm.inform_with' => 'метод автентифікації',
        ]);

        $encounter = $this->resolveEncounterModelForStandalone();
        if ($encounter === null) {
            return;
        }

        try {
            $employeeContext = app(MedicationRequestLifecycleService::class)->resolveEncounterEmployeeContext(
                $encounter,
                Auth::user()?->activeDoctorEmployee()?->id
            );

            $formData = $this->encounterEPrescriptionForm;
            $formData['program_id'] = $formData['program_id'] !== '' ? $formData['program_id'] : null;

            $this->encounterEPrescriptionRequestIdToSign = app(MedicationRequestLifecycleService::class)
                ->createEncounterDraft($encounter, $formData, $employeeContext);

            $this->showEncounterEPrescriptionDrawer = false;
            $this->actionType = 'sign_eprescription';
            $this->showSignatureModal = true;
        } catch (EHealthValidationException $exception) {
            $exception->report();
            $this->encounterEPrescriptionWarningMessage = $exception->getTranslatedMessage();
            $this->dispatch('flashMessage', [
                'type' => 'error',
                'message' => $this->encounterEPrescriptionWarningMessage,
            ]);
        } catch (\Throwable $exception) {
            Log::error('EncounterEdit: failed to create encounter eRx: '.$exception->getMessage());
            $this->encounterEPrescriptionWarningMessage = 'Не вдалося створити заявку на рецепт: '.$exception->getMessage();
            $this->dispatch('flashMessage', [
                'type' => 'error',
                'message' => $this->encounterEPrescriptionWarningMessage,
            ]);
        }
    }

    public function signEncounterEPrescription(): void
    {
        if (empty($this->encounterEPrescriptionRequestIdToSign)) {
            Session::flash('error', 'Не вибрано рецепт для підписання');
            $this->showSignatureModal = false;

            return;
        }

        $encounter = $this->resolveEncounterModelForStandalone();
        if ($encounter === null) {
            $this->showSignatureModal = false;

            return;
        }

        $requestRecord = MedicationRequestRequest::query()
            ->where('uuid', $this->encounterEPrescriptionRequestIdToSign)
            ->first();

        if ($requestRecord === null) {
            Session::flash('error', 'Рецепт не знайдено');
            $this->showSignatureModal = false;

            return;
        }

        try {
            $validated = $this->form->validate($this->form->signingRules());

            $result = app(MedicationRequestLifecycleService::class)->sign(
                $encounter,
                $requestRecord,
                [
                    'password' => $validated['password'],
                    'knedp' => $validated['knedp'],
                    'keyContainerUpload' => $validated['keyContainerUpload'],
                    'medication_unit' => $this->encounterEPrescriptionForm['medication_unit'] ?? 'од.',
                ],
                (string) ($this->encounterEPrescriptionForm['inform_with'] ?? $requestRecord->informWith ?? ''),
                0.0
            );

            $this->showSignatureModal = false;
            $this->actionType = null;
            $this->encounterEPrescriptionRequestIdToSign = null;
            $this->form->resetSigningFields();

            $message = $result['success_message']
                ?? 'Електронний рецепт успішно створено без плану лікування.';

            $this->dispatch('flashMessage', [
                'type' => 'success',
                'message' => $message,
            ]);
        } catch (EHealthValidationException $exception) {
            $exception->report();
            Session::flash('error', $exception->getTranslatedMessage());
            $this->showSignatureModal = false;
        } catch (\Throwable $exception) {
            Log::error('EncounterEdit: failed to sign encounter eRx: '.$exception->getMessage());
            Session::flash('error', 'Не вдалося підписати рецепт: '.$exception->getMessage());
            $this->showSignatureModal = false;
        }
    }

    protected function loadEncounterEPrescriptionAuthMethods(Encounter $encounter): void
    {
        $this->encounterEPrescriptionAuthMethods = [];
        $person = Person::find($encounter->person_id);
        if ($person === null || empty($person->uuid)) {
            return;
        }

        try {
            $authMethods = EHealth::person()->getAuthMethods($person->uuid)->getData();
            if (!is_array($authMethods)) {
                return;
            }

            $this->encounterEPrescriptionAuthMethods = collect($authMethods)->map(static function (array $method): array {
                $uuid = (string) ($method['id'] ?? $method['uuid'] ?? '');
                $type = (string) ($method['type'] ?? '');
                $phone = (string) ($method['phone_number'] ?? $method['value'] ?? '');

                return [
                    'uuid' => $uuid,
                    'type' => $type,
                    'label' => trim($type.($phone !== '' ? ' · '.$phone : '')),
                ];
            })->filter(static fn (array $m): bool => $m['uuid'] !== '')->values()->all();
        } catch (\Throwable $exception) {
            Log::warning('EncounterEdit: failed to load auth methods for eRx: '.$exception->getMessage());
        }
    }
}
