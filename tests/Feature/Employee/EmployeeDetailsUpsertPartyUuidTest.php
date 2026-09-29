<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Enums\Status;
use App\Jobs\EmployeeDetailsUpsert;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\Relations\Party;
use App\Repositories\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class EmployeeDetailsUpsertPartyUuidTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function relink_avoids_unique_violation_when_remote_uuid_belongs_to_another_party(): void
    {
        [$legalEntity, $employee, $localParty, $canonicalParty] = $this->makeConflictingParties();

        $job = new EmployeeDetailsUpsert($employee, $legalEntity, standalone: true);
        $this->associateExistingParty($job, $canonicalParty->uuid);

        Repository::employee()->updateDetails(
            $job->employee,
            [
                'uuid' => $canonicalParty->uuid,
                'first_name' => 'Xipyr',
                'last_name' => 'Batkovych',
                'tax_id' => '2589631475',
            ],
            [],
            [],
        );

        $job->employee->refresh();
        $localParty->refresh();
        $canonicalParty->refresh();

        $this->assertSame($canonicalParty->id, $job->employee->partyId);
        $this->assertNull($localParty->uuid);
        $this->assertSame($canonicalParty->uuid, $canonicalParty->fresh()->uuid);
    }

    #[Test]
    public function relink_is_noop_when_remote_uuid_is_still_free(): void
    {
        [$legalEntity, $employee, $localParty] = $this->makeConflictingParties(withCanonical: false);
        $freeUuid = (string) Str::uuid();

        $job = new EmployeeDetailsUpsert($employee, $legalEntity, standalone: true);
        $this->associateExistingParty($job, $freeUuid);

        $this->assertSame($localParty->id, $job->employee->partyId);
    }

    /**
     * @return array{0: LegalEntity, 1: Employee, 2: Party, 3?: Party}
     */
    private function makeConflictingParties(bool $withCanonical = true): array
    {
        $typeId = \Illuminate\Support\Facades\DB::table('legal_entity_types')->where('name', 'PRIMARY_CARE')->value('id')
            ?? \Illuminate\Support\Facades\DB::table('legal_entity_types')->insertGetId(['name' => 'PRIMARY_CARE']);

        $legalEntity = LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'sync_status' => 'COMPLETED',
            'legal_entity_type_id' => $typeId,
            'is_active' => true,
        ]);

        $localParty = Party::create([
            'uuid' => null,
            'first_name' => 'Local',
            'last_name' => 'Draft',
            'tax_id' => '1111111111',
            'birth_date' => '2000-02-10',
            'gender' => 'MALE',
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'employee_type' => 'DOCTOR',
            'status' => Status::APPROVED->value,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'position' => 'P1',
            'start_date' => now()->format('Y-m-d'),
            'party_id' => $localParty->id,
        ]);

        if (!$withCanonical) {
            return [$legalEntity, $employee, $localParty];
        }

        $canonicalParty = Party::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Xipyr',
            'last_name' => 'Batkovych',
            'tax_id' => '2589631475',
            'birth_date' => '2000-02-10',
            'gender' => 'MALE',
        ]);

        return [$legalEntity, $employee, $localParty, $canonicalParty];
    }

    private function associateExistingParty(EmployeeDetailsUpsert $job, string $partyUuid): void
    {
        $method = new ReflectionMethod(EmployeeDetailsUpsert::class, 'associateExistingPartyByUuid');
        $method->invoke($job, ['uuid' => $partyUuid]);
    }
}
