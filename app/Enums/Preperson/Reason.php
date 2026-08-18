<?php

declare(strict_types=1);

namespace App\Enums\Preperson;

use App\Traits\EnumUtils;

enum Reason: string
{
    use EnumUtils;

    case EMERGENCY_HOSPITALIZATION = 'EMERGENCY_HOSPITALIZATION';
    case POLICE_HOSPITALIZATION = 'POLICE_HOSPITALIZATION';
    case NEWBORN_WITHOUT_CERTIFICATE = 'NEWBORN_WITHOUT_CERTIFICATE';
    case OTHER_HOSPITALIZATION = 'OTHER_HOSPITALIZATION';

    /**
     * Human-readable label for the preperson registration reason.
     *
     * @return string
     */
    public function label(): string
    {
        return __('preperson.reasons.' . $this->value);
    }
}
