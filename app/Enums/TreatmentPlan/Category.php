<?php

declare(strict_types=1);

namespace App\Enums\TreatmentPlan;

enum Category: string
{
    case CLASS_23 = 'CLASS_23';
    case CLASS_24 = 'CLASS_24';
    case CLASS_25 = 'CLASS_25';

    // Temporary placeholders until we identify exact eHealth categories for Plans
    case PRIMARY_CARE = 'primary_care';
    case AMBULATORY = 'ambulatory';

    public function label(): string
    {
        return match ($this) {
            self::CLASS_23 => 'Клас 23',
            self::CLASS_24 => 'Клас 24',
            self::CLASS_25 => 'Клас 25',
            self::PRIMARY_CARE => 'Первинна медична допомога',
            self::AMBULATORY => 'Амбулаторна медична допомога',
        };
    }
}
