<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api\Responses\Collections;

use Illuminate\Support\Collection;

/** @extends Collection<string, mixed> */
class EmployeeRequestDetails extends Collection
{
    public array $providedFields;

    public function __construct(iterable|null $items = [])
    {
        parent::__construct($items);

        $this->providedFields = array_keys($this->items);
    }
}
