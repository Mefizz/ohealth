<?php

declare(strict_types=1);

namespace App\Exceptions\EHealth;

use RuntimeException;

class EHealthTokenScopeException extends RuntimeException
{
    public function __construct(string $scope)
    {
        parent::__construct('Synchronization paused: token scope is missing or unknown: ' . $scope, 403);
    }
}
