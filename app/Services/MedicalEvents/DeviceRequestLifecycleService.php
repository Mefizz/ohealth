<?php

declare(strict_types=1);

namespace App\Services\MedicalEvents;

use App\Classes\eHealth\Api\DeviceRequest;
use App\Contracts\EHealthRequestLifecycleContract;

class DeviceRequestLifecycleService extends EHealthRequestLifecycleService implements EHealthRequestLifecycleContract
{
    public function preQualify(array $payload): array
    {
        return $this->callEHealth('Prequalify', static fn (): array => DeviceRequest::preQualify($payload));
    }

    public function createDraft(array $payload): array
    {
        return $this->callEHealth('Create Draft', static fn (): array => DeviceRequest::createDeviceRequest($payload));
    }

    public function sign(string $id, array $payload): array
    {
        $payload = $this->normalizeSignedPayload($payload);

        return $this->callEHealth('Sign', static fn (): array => DeviceRequest::signDeviceRequest($id, $payload));
    }

    public function reject(string $id, array $payload): array
    {
        return $this->callEHealth('Reject', static fn (): array => DeviceRequest::rejectDeviceRequest($id, $payload));
    }

    protected function requestType(): string
    {
        return 'Device Request';
    }

    /**
     * Device Request expects the KEP blob under signed_device_request_request.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSignedPayload(array $payload): array
    {
        if (isset($payload['signed_content']) && !isset($payload['signed_device_request_request'])) {
            $payload['signed_device_request_request'] = $payload['signed_content'];
            unset($payload['signed_content']);
        }

        $payload['signed_content_encoding'] ??= 'base64';

        return $payload;
    }
}
