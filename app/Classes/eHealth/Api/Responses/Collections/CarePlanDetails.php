<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Responses\Collections;

use Illuminate\Support\Collection;

/**
 * Validated Care Plan details using eHealth field names and typed codeable concepts.
 *
 * @extends Collection<string, mixed>
 */
class CarePlanDetails extends Collection
{
    protected const array CODEABLE_CONCEPTS = ['category', 'terms_of_service', 'status_reason'];

    public function __construct(iterable|null $items = [])
    {
        parent::__construct($items);

        foreach (self::CODEABLE_CONCEPTS as $field) {
            if (is_array($this->get($field))) {
                $this->put($field, new EhealthCodeableConcept($this->get($field)));
            }
        }
    }

    /**
     * Restore the wire structure for consumers such as cancellation signing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = $this->toBase()->toArray();

        foreach (self::CODEABLE_CONCEPTS as $field) {
            if ($this->get($field) instanceof EhealthCodeableConcept && isset($items[$field]['coding'])) {
                $items[$field]['coding'] = array_map(
                    static fn (EhealthCoding $coding): array => $coding->all(),
                    $items[$field]['coding']
                );
            }
        }

        return $items;
    }
}
