<?php

declare(strict_types=1);

namespace App\Enums;

enum CarePlanActivityKind: string
{
    case SERVICE_REQUEST = 'service_request';
    case MEDICATION_REQUEST = 'medication_request';
    case DEVICE_REQUEST = 'device_request';
}
