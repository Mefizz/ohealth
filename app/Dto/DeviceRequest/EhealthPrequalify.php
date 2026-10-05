<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use App\Dto\Shared\EhealthReference as EHealthReference;
use App\Dto\Concerns\PreservesEhealthDocumentValues;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class EhealthPrequalify
{
    use PreservesEhealthDocumentValues;
    #[Map(source: 'request', transform: MapDeviceRequestBody::class)]
    public Ehealth $device_request;

    #[Map(if: 'count', transform: new MapCollection(targetClass: EHealthReference::class))]
    public ?array $programs = null;

    protected function normalizeMappedData(array $data, CamelCaseToSnakeCaseNameConverter $converter): array
    {
        $data['device_request'] = $this->device_request->toArray();

        return $data;
    }
}
