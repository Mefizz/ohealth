<?php

declare(strict_types=1);

namespace App\Rules\ContractRules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidReimbursementPeriod implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(
        protected ?string $startDate,
        protected ?string $previousRequestId,
        protected string $format
    ) {
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($this->startDate) || empty($value)) {
            return;
        }

        try {
            $startDate = CarbonImmutable::createFromFormat($this->format, $this->startDate);
            $endDate = CarbonImmutable::createFromFormat($this->format, $value);

            $maxDays = config('ehealth.reimbursement_contract_max_period_day', 1096);

            if (!empty($this->previousRequestId)) {
                // Prolongation limit: maximum 3 months
                if ($startDate->addMonths(3)->lessThan($endDate)) {
                    $fail('Продовження дії договору можливе не більше ніж на три місяці');
                }
            } else {
                // Standard reimbursement contract limit
                if ($startDate->diffInDays($endDate) > $maxDays) {
                    $fail(
                        'Різниця між датою закінчення договору та датою початку договору '
                        . 'не повинна перевищувати ' . $maxDays . ' днів'
                    );
                }
            }
        } catch (\Exception) {
            // Let standard format validation handle the error
        }
    }
}
