<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest;

/** A complete search document, with import rules distinct from partial GET sync and use results. */
final readonly class ExternalResponse
{
    public function __construct(public array $data)
    {
    }
}
