<?php

declare(strict_types=1);

namespace App\Enums\EmployeeRole;

use App\Traits\EnumUtils;

/**
 * @see https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/18832326729/Employee+Role+ENT-025#%D0%A1%D1%82%D0%B0%D1%82%D1%83%D1%81%D0%B8
 */
enum Status: string
{
    use EnumUtils;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('forms.status.active'),
            self::INACTIVE => __('forms.status.non_active')
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'badge-green',
            self::INACTIVE => 'badge-red'
        };
    }
}
