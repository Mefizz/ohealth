<?php

declare(strict_types=1);

namespace App\Rules\ContractRules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SameYearAs implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(
        protected ?string $compareDate,
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
        if (empty($this->compareDate) || empty($value)) {
            return;
        }

        try {
            $date1 = CarbonImmutable::createFromFormat($this->format, $this->compareDate);
            $date2 = CarbonImmutable::createFromFormat($this->format, $value);

            if ($date1->year !== $date2->year) {
                $fail('Рік початку дії договору та рік кінця дії мають співпадати');
            }
        } catch (\Exception) {
            // Safe fallback, let format validation handle it
        }
    }
}
