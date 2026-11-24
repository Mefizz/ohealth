<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Responses\Collections;

use App\Exceptions\EHealth\EHealthValidationException;
use Illuminate\Support\Collection;

/** @extends Collection<int, array{program_id: string, program_name: string, status: string, rejection_reason?: string}> */
class ServiceRequestPrequalification extends Collection
{
    public function assertEligible(): void
    {
        foreach ($this->items as $verdict) {
            if ($verdict['status'] === 'INVALID') {
                $reason = trim($verdict['rejection_reason'] ?? '');

                throw new EHealthValidationException([
                    'error' => ['message' => $reason !== '' ? $reason : __('care-plan.referral_prequalify_failed')],
                ]);
            }
        }
    }
}
