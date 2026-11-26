<?php

declare(strict_types=1);

namespace App\Dto\EmployeeRequest;

use App\Classes\eHealth\Api\Responses\Collections\EmployeeRequestDetails;
use App\Contracts\Dto\Model as ModelContract;
use App\Core\Arr;
use App\Models\Employee\EmployeeRequest;
use Symfony\Component\ObjectMapper\Attribute\Map;

/** Validated details; absent optional fields preserve the stored request and revision. */
#[Map(source: EmployeeRequestDetails::class)]
class Model implements ModelContract
{
    public array $providedFields = [];

    #[Map(source: '[uuid]')]
    public string $uuid;

    #[Map(source: '[status]')]
    public string $status;

    #[Map(source: '[position]')]
    public string $position;

    #[Map(source: '[employee_type]')]
    public string $employeeType;

    #[Map(source: '[division_uuid]')]
    public ?string $divisionUuid = null;

    #[Map(source: '[legal_entity_uuid]')]
    public ?string $legalEntityUuid = null;

    #[Map(source: '[start_date]')]
    public ?string $startDate = null;

    #[Map(source: '[end_date]')]
    public ?string $endDate = null;

    #[Map(source: '[inserted_at]')]
    public ?string $insertedAt = null;

    #[Map(source: '[updated_at]')]
    public ?string $updatedAt = null;

    #[Map(source: '[party]')]
    public ?array $party = null;

    #[Map(source: '[doctor]')]
    public ?array $doctor = null;

    #[Map(source: '[med_admin]')]
    public ?array $medAdmin = null;

    #[Map(source: '[med_coordinator]')]
    public ?array $medCoordinator = null;

    #[Map(source: '[pharmacist]')]
    public ?array $pharmacist = null;

    #[Map(source: '[specialist]')]
    public ?array $specialist = null;

    #[Map(source: '[assistant]')]
    public ?array $assistant = null;

    #[Map(source: '[laborant]')]
    public ?array $laborant = null;

    public function toModel(?EmployeeRequest $request = null): EmployeeRequest
    {
        $request ??= new EmployeeRequest();

        foreach (['uuid', 'status', 'position', 'employeeType', 'divisionUuid', 'legalEntityUuid', 'startDate', 'endDate', 'insertedAt'] as $field) {
            if (in_array(str()->snake($field), $this->providedFields, true)) {
                $request->setAttribute($field, $this->{$field});
            }
        }

        if ($this->party !== null && array_key_exists('email', $this->party)) {
            $request->email = $this->party['email'];
        }

        return $request;
    }

    public function toRevisionData(array $stored = [], ?int $divisionId = null): array
    {
        foreach (['position', 'employeeType', 'startDate', 'endDate'] as $field) {
            $key = str()->snake($field);
            if (in_array($key, $this->providedFields, true)) {
                $stored['employee_request_data'][$key] = $this->{$field};
            }
        }

        if (in_array('division_uuid', $this->providedFields, true)) {
            $stored['employee_request_data']['division_id'] = $divisionId;
        }

        if ($this->party !== null) {
            $stored['party'] = array_replace($stored['party'] ?? [], Arr::except($this->party, ['documents', 'phones']));
            foreach (['documents', 'phones'] as $relation) {
                if (array_key_exists($relation, $this->party)) {
                    $stored[$relation] = $this->party[$relation];
                }
            }
        }

        foreach (['doctor', 'medAdmin', 'medCoordinator', 'pharmacist', 'specialist', 'assistant', 'laborant'] as $field) {
            $key = str()->snake($field);
            if ($this->{$field} !== null) {
                $stored[$key] = array_replace($stored[$key] ?? [], $this->{$field});
            }
        }

        return $stored;
    }
}
