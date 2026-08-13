<?php

declare(strict_types=1);

namespace App\Enums\Person;

use App\Traits\EnumUtils;
use App\Traits\ResolvesEHealthStatus;

/**
 * Statuses of a patient approval (consent) granting an employee access to a resource.
 *
 * `approvals.status` currently holds two spellings: eHealth and our own create path write the
 * upper case NEW / APPROVED, while approval sync derives the lower case active / inactive /
 * pending. Both are listed so existing rows resolve; {@see ResolvesEHealthStatus::resolve()}
 * makes comparisons case-insensitive. Settling the column on one spelling needs a data
 * migration and is deliberately not done here.
 */
enum ApprovalStatus: string
{
    use EnumUtils;
    use ResolvesEHealthStatus;

    case NEW = 'NEW';
    case PENDING = 'pending';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * The patient has confirmed the request, so the granted employee may use the resource.
     */
    public function isGranted(): bool
    {
        return $this === self::ACTIVE || $this === self::APPROVED;
    }

    /**
     * Created but not confirmed by the patient yet, so it can be re-requested.
     */
    public function isAwaitingPatient(): bool
    {
        return $this === self::NEW || $this === self::PENDING;
    }

    public function label(): string
    {
        return __('care-plan.approval_status.'.strtolower($this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE,
            self::APPROVED => 'badge-green',

            self::NEW,
            self::PENDING => 'badge-yellow',

            self::INACTIVE => 'badge-dark',
        };
    }
}
