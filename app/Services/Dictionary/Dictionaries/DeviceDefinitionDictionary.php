<?php

declare(strict_types=1);

namespace App\Services\Dictionary\Dictionaries;

use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\EHealthResponse;
use App\Services\Dictionary\DictionaryInterface;
use App\Services\Dictionary\RequiresAuthentication;

class DeviceDefinitionDictionary implements DictionaryInterface, RequiresAuthentication
{
    /**
     * Dictionary unique identifier key.
     */
    public const string KEY = 'dictionaries.device_definition';

    /**
     * Get the dictionary key.
     *
     * @return string Dictionary identifier for caching and registry
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $page = 1): EHealthResponse
    {
        return EHealth::deviceDefinition()->getMany(['page' => $page]);
    }
}
