<?php

declare(strict_types=1);

namespace App\Rules\DivisionRules;

use Closure;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;

class LocationRule implements ValidationRule
{
    protected string $message;

    public function __construct(protected array $division)
    {
    }

    /**
     * Run the validation rule. Check that location longitude and latitude specified in pair simultaneously
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            return;
        }

        $fieldName = str_ends_with($attribute, '.longitude') ? 'longitude' : 'latitude';

        $maximum = $fieldName === 'longitude' ? 180 : 90;

        if (!is_finite((float) $value) || BigDecimal::of(trim((string) $value))->abs()->isGreaterThan($maximum)) {
            $fail(__("divisions.errors.location.{$fieldName}_misformat"));

            return;
        }
    }
}
