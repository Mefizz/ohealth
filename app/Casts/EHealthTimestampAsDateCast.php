<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the value as a full timestamp (driver-safe) but presents it as a date only.
 *
 * Use it for eHealth timestamp columns whose time component should be kept in the
 * database while only the date is shown to the user.
 */
class EHealthTimestampAsDateCast implements CastsAttributes
{
    /**
     * Cast the stored timestamp to a date-only string for display.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (!$value) {
            return null;
        }

        return CarbonImmutable::parse($value)->format(config('app.date_format'));
    }

    /**
     * Prepare the given value for storage as a full timestamp.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (!$value) {
            return null;
        }

        return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
    }
}
