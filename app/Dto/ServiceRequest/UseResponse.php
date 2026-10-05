<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

/** The use action imports a minimal record, with different defaults from a partial GET sync. */
final readonly class UseResponse
{
    public function __construct(public array $data)
    {
    }
}
