<?php

declare(strict_types=1);

namespace App\Dto\DeviceRequest;

use App\Mapping\EHealth\Shared\EHealthReference;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

final class EhealthPrequalify
{
    #[Map(source: 'request', transform: MapDeviceRequestBody::class)]
    public Ehealth $device_request;

    #[Map(if: 'count', transform: new MapCollection(targetClass: EHealthReference::class))]
    public ?array $programs = null;
}
