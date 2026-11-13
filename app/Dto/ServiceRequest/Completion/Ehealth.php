<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Completion;

use App\Core\Arr;
use App\Dto\FormCollection;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: FormCollection::class)]
class Ehealth
{
    #[Map(source: '[resourceUuid]')]
    public string $resourceUuid;

    #[Map(source: '[resourceType]')]
    public string $resourceType;

    #[Map(source: '[programService?]')]
    public ?array $programService = null;

    #[Map(source: '[statusReason?]')]
    public ?array $statusReason = null;

    public function toArray(): array
    {
        $data = ['completed_with' => ['identifier' => [
            'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => $this->resourceType]]],
            'value' => $this->resourceUuid,
        ]]];
        if ($this->programService !== null) {
            $data['program_service'] = $this->programService;
        }
        if ($this->statusReason !== null) {
            $data['status_reason'] = $this->statusReason;
        }

        return Arr::toSnakeCase($data);
    }
}
