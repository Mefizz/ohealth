<?php

declare(strict_types=1);

namespace App\Services\Employee;

use App\Models\Division;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use UnexpectedValueException;

class EmployeeLegalEntityGuard
{
    public function assertRemote(array $data, LegalEntity $legalEntity, ?string $requestUuid = null): void
    {
        $remoteLegalEntityUuid = $data['legal_entity_uuid'] ?? $data['legal_entity_id'] ?? null;

        if (!is_string($remoteLegalEntityUuid) || strtolower($remoteLegalEntityUuid) !== strtolower($legalEntity->uuid)) {
            throw new UnexpectedValueException('Employee request does not belong to the synchronization legal entity.');
        }

        if ($requestUuid !== null && ($data['uuid'] ?? $data['id'] ?? null) !== $requestUuid) {
            throw new UnexpectedValueException('Employee request details do not match the requested UUID.');
        }

        foreach ([Employee::class => $data['employee_id'] ?? null, Division::class => $data['division_uuid'] ?? $data['division_id'] ?? null] as $model => $uuid) {
            if (is_string($uuid) && \Illuminate\Support\Str::isUuid($uuid) && $model::where('uuid', $uuid)->where('legal_entity_id', '!=', $legalEntity->id)->exists()) {
                throw new UnexpectedValueException('Employee request references another legal entity.');
            }
        }
    }

    public function assertEmployeeUuid(string $uuid, LegalEntity $legalEntity): void
    {
        if (Employee::where('uuid', $uuid)->where('legal_entity_id', '!=', $legalEntity->id)->exists()) {
            throw new UnexpectedValueException('Employee belongs to another legal entity.');
        }
    }

    public function prepareEmployeeBatch(array $employees, LegalEntity $legalEntity): array
    {
        foreach ($employees as &$employee) {
            $this->assertEmployeeUuid($employee['uuid'], $legalEntity);
            $employee['legal_entity_id'] = $legalEntity->id;
            $employee['legal_entity_uuid'] = $legalEntity->uuid;
        }
        unset($employee);

        return $employees;
    }

    public function assertLocal(EmployeeRequest $request, LegalEntity $legalEntity): void
    {
        if (!$request->belongsToLegalEntity($legalEntity)) {
            throw new UnexpectedValueException('Employee request has inconsistent legal entity references.');
        }
    }
}
