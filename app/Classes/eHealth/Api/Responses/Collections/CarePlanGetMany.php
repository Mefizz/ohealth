<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Responses\Collections;

use Illuminate\Support\Collection;

/**
 * Validated short Care Plan results from patient search, patient summary or requisition lookup.
 * Requisition results also include patient identity.
 *
 * @extends Collection<int, CarePlanSummary>
 */
class CarePlanGetMany extends Collection
{
    /**
     * @param  iterable<int, array<string, mixed>|CarePlanSummary>|null  $items
     */
    public function __construct(iterable|null $items = [])
    {
        parent::__construct($items);

        $this->items = array_map(
            static fn (array|CarePlanSummary $item): CarePlanSummary => $item instanceof CarePlanSummary
                ? $item
                : new CarePlanSummary($item),
            $this->items
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->toBase()->toArray();
    }
}
