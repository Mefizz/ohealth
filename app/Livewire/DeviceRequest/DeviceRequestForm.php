<?php

declare(strict_types=1);

namespace App\Livewire\DeviceRequest;

use App\Models\LegalEntity;
use App\Services\MedicalEvents\DeviceRequestLifecycleService;
use Exception;
use Livewire\Component;

class DeviceRequestForm extends Component
{
    public LegalEntity $legalEntity;

    public string $patientId = '';
    public string $medicalProgram = '';
    public string $deviceType = '';
    public string $quantity = '';

    public bool $isDraftCreated = false;
    public ?string $draftId = null;
    public ?string $statusMessage = null;

    protected array $rules = [
        'patientId' => 'required|string',
        'medicalProgram' => 'required|string',
        'deviceType' => 'required|string',
        'quantity' => 'required|numeric|min:1',
    ];

    public function preQualify(DeviceRequestLifecycleService $service)
    {
        $this->validate();

        try {
            $payload = [
                'person_id' => $this->patientId,
                'programs' => [
                    ['id' => $this->medicalProgram]
                ]
            ];

            $response = $service->preQualify($payload);

            $this->statusMessage = "PreQualify успішно пройдено. Можна створювати чернетку.";
        } catch (Exception $e) {
            $this->statusMessage = "Помилка PreQualify: " . $e->getMessage();
        }
    }

    public function createDraft(DeviceRequestLifecycleService $service)
    {
        $this->validate();

        try {
            $payload = [
                'person_id' => $this->patientId,
                'program' => $this->medicalProgram,
                'code' => [
                    'coding' => [
                        [
                            'system' => 'eHealth/SNOMED',
                            'code' => $this->deviceType
                        ]
                    ]
                ],
                'quantity' => (int)$this->quantity
            ];

            $response = $service->createDraft($payload);

            $this->isDraftCreated = true;
            $this->draftId = $response['id'] ?? 'dummy-uuid-device-1234';

            $this->statusMessage = "Чернетка медичного виробу створена (ID: {$this->draftId}). Очікується підпис КЕП.";
        } catch (Exception $e) {
            $this->statusMessage = "Помилка створення чернетки: " . $e->getMessage();
        }
    }

    public function sign(DeviceRequestLifecycleService $service)
    {
        if (!$this->isDraftCreated || !$this->draftId) {
            $this->statusMessage = "Спершу створіть чернетку!";

            return;
        }

        try {
            // Mock KEП signing payload
            $payload = [
                'signed_device_request_request' => base64_encode(json_encode(['id' => $this->draftId, 'status' => 'ACTIVE'])),
                'signed_content_encoding' => 'base64',
            ];

            $service->sign($this->draftId, $payload);

            $this->statusMessage = "Призначення успішно підписано КЕП та переведено у статус «Активний»!";

            // Dispatch event to parent to refresh list
            $this->dispatch('device-request-created');
        } catch (Exception $e) {
            $this->statusMessage = "Помилка підписання: " . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.device-request.device-request-form');
    }
}
