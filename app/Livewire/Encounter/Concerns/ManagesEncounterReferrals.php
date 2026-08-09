<?php

declare(strict_types=1);

namespace App\Livewire\Encounter\Concerns;

use App\Classes\eHealth\EHealth;
use App\Enums\Person\EncounterStatus;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\MedicalEvents\Sql\Encounter;
use App\Models\MedicalEvents\Sql\ServiceRequestRequest;
use App\Models\Person\Person;
use App\Repositories\MedicalEvents\Repository;
use App\Services\MedicalEvents\EHealthJobResolver;
use App\Services\MedicalEvents\Mappers\ServiceRequestMapper;
use App\Services\MedicalEvents\ReferralRequestLifecycleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

trait ManagesEncounterReferrals
{
    // Uses ResolvesEncounterStandaloneContext via EncounterEdit.

    public bool $showEncounterReferralDrawer = false;

    /** @var array<string, mixed> */
    public array $encounterReferralForm = [];

    /** @var list<array{uuid: string, type: string, label: string}> */
    public array $encounterReferralAuthMethods = [];

    public ?string $encounterReferralRequestIdToSign = null;

    public string $encounterReferralWarningMessage = '';

    public function openEncounterReferralDrawer(): void
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
                'message' => 'Електронне направлення без плану лікування можна створити лише після завершення взаємодії.',
            ]);

            return;
        }

        $this->loadEncounterReferralAuthMethods($encounter);
        $start = now()->addHour();

        $this->encounterReferralForm = [
            'kind' => 'service_request',
            'service_id' => '',
            'category' => 'procedure',
            'quantity' => '1',
            'priority' => 'routine',
            'started_at' => $start->format('d.m.Y'),
            'ended_at' => $start->copy()->addMonths(3)->format('d.m.Y'),
            'program_id' => '',
            'note' => '',
            'patient_instruction' => '',
            'inform_with' => $this->encounterReferralAuthMethods[0]['uuid'] ?? '',
            'reason_reference' => [],
        ];

        $this->encounterReferralWarningMessage = '';
        $this->showEncounterReferralDrawer = true;
    }

    public function closeEncounterReferralDrawer(): void
    {
        $this->showEncounterReferralDrawer = false;
        $this->encounterReferralWarningMessage = '';
    }

    public function validateEncounterReferral(): void
    {
        $this->encounterReferralWarningMessage = '';

        $this->validate([
            'encounterReferralForm.service_id' => 'required|string|uuid',
            'encounterReferralForm.category' => 'required|string',
            'encounterReferralForm.quantity' => 'required|numeric|min:0.01',
            'encounterReferralForm.priority' => 'required|in:routine,urgent,asap,stat',
            'encounterReferralForm.started_at' => 'required|date_format:d.m.Y',
            'encounterReferralForm.ended_at' => 'required|date_format:d.m.Y|after_or_equal:encounterReferralForm.started_at',
        ], [], [
            'encounterReferralForm.service_id' => 'код послуги',
            'encounterReferralForm.category' => 'категорія',
            'encounterReferralForm.quantity' => 'кількість',
            'encounterReferralForm.priority' => 'пріоритет',
            'encounterReferralForm.started_at' => 'дата початку',
            'encounterReferralForm.ended_at' => 'дата закінчення',
        ]);

        $encounter = $this->resolveEncounterModelForStandalone();
        if ($encounter === null) {
            return;
        }

        try {
            $employeeContext = app(ReferralRequestLifecycleService::class)->resolveEncounterEmployeeContext(
                $encounter,
                Auth::user()?->activeDoctorEmployee()?->id
            );

            $formData = $this->encounterReferralForm;
            $formData['program_id'] = $formData['program_id'] !== '' ? $formData['program_id'] : null;
            $formData['kind'] = 'service_request';

            $this->encounterReferralRequestIdToSign = app(ReferralRequestLifecycleService::class)->createEncounterDraft(
                $encounter,
                $formData,
                (float) $formData['quantity'],
                $employeeContext
            );

            $this->showEncounterReferralDrawer = false;
            $this->actionType = 'sign_referral';
            $this->showSignatureModal = true;
        } catch (EHealthValidationException $exception) {
            $exception->report();
            $this->encounterReferralWarningMessage = $exception->getFormattedMessage();
            Session::flash('error', $this->encounterReferralWarningMessage);
        } catch (\Throwable $exception) {
            Log::error('EncounterEdit: failed to create encounter referral: '.$exception->getMessage());
            $this->encounterReferralWarningMessage = 'Не вдалося створити направлення: '.$exception->getMessage();
            Session::flash('error', $this->encounterReferralWarningMessage);
        }
    }

    public function signEncounterReferral(): void
    {
        if (empty($this->encounterReferralRequestIdToSign)) {
            Session::flash('error', 'Не вибрано направлення для підписання');
            $this->showSignatureModal = false;

            return;
        }

        $encounter = $this->resolveEncounterModelForStandalone();
        if ($encounter === null) {
            $this->showSignatureModal = false;

            return;
        }

        $requestRecord = ServiceRequestRequest::query()
            ->where('uuid', $this->encounterReferralRequestIdToSign)
            ->first();

        if ($requestRecord === null) {
            Session::flash('error', 'Направлення не знайдено');
            $this->showSignatureModal = false;

            return;
        }

        try {
            $validated = $this->form->validate($this->form->signingRules());
            $person = Person::find($encounter->person_id);
            if ($person === null || empty($person->uuid)) {
                throw new \RuntimeException('Пацієнта не знайдено');
            }

            $employeeContext = app(ReferralRequestLifecycleService::class)->resolveEncounterEmployeeContext(
                $encounter,
                $requestRecord->employee_id ?? Auth::user()?->activeDoctorEmployee()?->id
            );

            $dbData = [
                'uuid' => $requestRecord->uuid,
                'employee_id' => $employeeContext['employee_id'],
                'division_id' => $employeeContext['division_id'],
                'based_on_id' => null,
                'context_id' => $encounter->id,
                'quantity' => $requestRecord->quantity,
                'quantity_system' => $requestRecord->quantity_system ?: 'SERVICE_UNIT',
                'quantity_code' => $requestRecord->quantity_code ?: 'PIECE',
                'intent' => $requestRecord->intent ?? 'order',
                'category' => $requestRecord->category,
                'program_id' => $requestRecord->program_id,
                'priority' => $requestRecord->priority ?? 'routine',
                'note' => $requestRecord->note,
                'patient_instruction' => $requestRecord->patient_instruction,
                'reason_reference' => $requestRecord->reason_reference,
                'inform_with' => $requestRecord->inform_with,
                'supporting_info' => $requestRecord->supporting_info,
                'started_at' => $requestRecord->started_at instanceof \DateTimeInterface
                    ? $requestRecord->started_at->format('Y-m-d')
                    : (string) $requestRecord->started_at,
                'ended_at' => $requestRecord->ended_at instanceof \DateTimeInterface
                    ? $requestRecord->ended_at->format('Y-m-d')
                    : (string) $requestRecord->ended_at,
                'based_on_uuid' => null,
                'service_id' => $requestRecord->service_id,
            ];

            $uuids = [
                'person_uuid' => $person->uuid,
                'encounter_uuid' => $encounter->uuid,
                'episode_uuid' => $encounter->episode?->value ?? null,
                'employee_uuid' => $employeeContext['employee_uuid'],
                'legal_entity_uuid' => $employeeContext['legal_entity_uuid'],
            ];

            $signPayload = (new ServiceRequestMapper())->toCreateSignedContent($dbData, $uuids, null, null);

            $signedContent = signatureService()->signData(
                $signPayload,
                $validated['password'],
                $validated['knedp'],
                $validated['keyContainerUpload'],
                Auth::user()->party->taxId
            );

            $eHealthResponse = EHealth::serviceRequest()->createSigned($person->uuid, [
                'signed_data' => $signedContent,
                'signed_data_encoding' => 'base64',
            ]);

            $finalResponse = app(EHealthJobResolver::class)->resolve($eHealthResponse->getData());
            app(EHealthJobResolver::class)->assertSuccessful($finalResponse);

            $entity = isset($finalResponse['result'][0])
                ? $finalResponse['result'][0]
                : ($finalResponse['result'] ?? $finalResponse);

            $dbData['status'] = $entity['status'] ?? ($finalResponse['status'] ?? 'active');
            $dbData['request_number'] = $entity['request_number'] ?? $entity['requisition'] ?? $dbData['request_number'] ?? null;
            $dbData['uuid'] = $entity['id'] ?? $dbData['uuid'];

            Repository::serviceRequest()->store($dbData, (int) $encounter->person_id);

            $this->showSignatureModal = false;
            $this->actionType = null;
            $this->encounterReferralRequestIdToSign = null;
            $this->form->resetSigningFields();

            $this->dispatch('flashMessage', [
                'type' => 'success',
                'message' => 'Електронне направлення успішно створено без плану лікування (№ '
                    .($dbData['request_number'] ?? $dbData['uuid']).').',
            ]);
        } catch (EHealthValidationException $exception) {
            $exception->report();
            Session::flash('error', $exception->getFormattedMessage());
            $this->showSignatureModal = false;
        } catch (\Throwable $exception) {
            Log::error('EncounterEdit: failed to sign encounter referral: '.$exception->getMessage());
            Session::flash('error', 'Не вдалося підписати направлення: '.$exception->getMessage());
            $this->showSignatureModal = false;
        }
    }

    protected function loadEncounterReferralAuthMethods(Encounter $encounter): void
    {
        $this->encounterReferralAuthMethods = [];
        $person = Person::find($encounter->person_id);
        if ($person === null || empty($person->uuid)) {
            return;
        }

        try {
            $authMethods = EHealth::person()->getAuthMethods($person->uuid)->getData();
            if (!is_array($authMethods)) {
                return;
            }

            $this->encounterReferralAuthMethods = collect($authMethods)->map(static function (array $method): array {
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
            Log::warning('EncounterEdit: failed to load auth methods for referral: '.$exception->getMessage());
        }
    }

}
