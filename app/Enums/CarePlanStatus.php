<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumUtils;

enum CarePlanStatus: string
{
    use EnumUtils;

    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ON_HOLD = 'on-hold';
    case REVOKED = 'revoked';
    case COMPLETED = 'completed';
    case ENTERED_IN_ERROR = 'entered-in-error';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('forms.status.draft'),
            self::ACTIVE => __('forms.status.active'),
            self::ON_HOLD => __('forms.status.on_hold'),
            self::REVOKED => __('forms.status.revoked'),
            self::COMPLETED => __('forms.status.completed'),
            self::ENTERED_IN_ERROR => __('forms.status.entered_in_error'),
            self::UNKNOWN => __('forms.status.unknown'),
        };
    }
}
