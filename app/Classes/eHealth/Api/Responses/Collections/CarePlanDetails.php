<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Responses\Collections;

use Illuminate\Support\Collection;

/**
 * Validated native CarePlan data; nested concepts use the shared mapper inputs.
 *
 * @extends Collection<string, mixed>
 */
class CarePlanDetails extends Collection
{
    /** The complete native response, including fields outside the local projection. */
    public array $native;

    public function __construct(iterable|null $items = [])
    {
        parent::__construct($items);
        $this->native = $this->items;

        foreach (['category', 'terms_of_service', 'status_reason'] as $field) {
            if (is_array($this->get($field))) {
                $this->put($field, new EhealthCodeableConcept($this->get($field)));
            }
        }
        if ($this->has('addresses')) {
            $this->put('addresses', array_map(
                static fn (array|EhealthCodeableConcept $item): EhealthCodeableConcept => $item instanceof EhealthCodeableConcept
                    ? $item
                    : new EhealthCodeableConcept($item),
                $this->get('addresses')
            ));
        }
    }
}
