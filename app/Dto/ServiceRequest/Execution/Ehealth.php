<?php

declare(strict_types=1);

namespace App\Dto\ServiceRequest\Execution;

use App\Dto\FormCollection;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: FormCollection::class)]
class Ehealth
{
    #[Map(source: '[employeeUuid?]')]
    public ?string $employeeUuid = null;

    #[Map(source: '[divisionUuid?]')]
    public ?string $divisionUuid = null;

    #[Map(source: '[legalEntityUuid?]')]
    public ?string $legalEntityUuid = null;

    #[Map(source: '[programId?]')]
    public ?string $programId = null;

    public function toArray(): array
    {
        $data = [];
        foreach ([
            'used_by_employee' => [$this->employeeUuid, 'employee'],
            'used_by_division' => [$this->divisionUuid, 'division'],
            'used_by_legal_entity' => [$this->legalEntityUuid, 'legal_entity'],
            'program' => [$this->programId, 'medical_program'],
        ] as $field => [$value, $type]) {
            if ($value !== null && $value !== '') {
                $data[$field] = ['identifier' => [
                    'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => $type]]],
                    'value' => $value,
                ]];
            }
        }

        return $data;
    }
}
