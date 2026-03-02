<?php

declare(strict_types=1);

namespace App\Enums\TreatmentPlan;

enum TermsService: string
{
    case AMBULATORY = 'ambulatory';
    case INPATIENT = 'inpatient';
    case HOME = 'home';

    public function label(): string
    {
        return match ($this) {
            self::AMBULATORY => 'Амбулаторно',
            self::INPATIENT => 'Стаціонарно',
            self::HOME => 'Вдома',
        };
    }
}
