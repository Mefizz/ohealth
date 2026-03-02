<?php

declare(strict_types=1);

namespace App\Enums\TreatmentPlan;

enum Intention: string
{
    case ORDER = 'order';
    case PLAN = 'plan';
    case PROPOSAL = 'proposal';

    public function label(): string
    {
        return match ($this) {
            self::ORDER => 'Замовлення (Order)',
            self::PLAN => 'План (Plan)',
            self::PROPOSAL => 'Пропозиція (Proposal)',
        };
    }
}
