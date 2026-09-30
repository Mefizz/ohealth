<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Enums\Status;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\Relations\Party;
use App\Repositories\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatePartyByUuidRelinkTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function update_details_relinks_when_remote_uuid_belongs_to_another_party(): void
    {
        [, $employee, $localParty, $canonicalParty] = $this->makeConflictingParties();

        Repository::employee()->updateDetails(
            $employee,
            [
                'uuid' => $canonicalParty->uuid,
                'first_name' => 'Xipyr',
                'last_name' => 'Batkovych',
                'tax_id' => '2589631475',
            ],
            [],
            [],
        );

        $employee->refresh();
        $localParty->refresh();
        $canonicalParty->refresh();

        $this->assertSame($canonicalParty->id, $employee->partyId);
        $this->assertNull($localParty->uuid);
        $this->assertSame($canonicalParty->uuid, $canonicalParty->fresh()->uuid);
        $this->assertSame('Xipyr', $canonicalParty->firstName);
    }

    #[Test]
    public function update_details_assigns_uuid_to_local_party_when_still_free(): void
    {
        [, $employee, $localParty] = $this->makeConflictingParties(withCanonical: false);
        $freeUuid = (string) Str::uuid();

        Repository::employee()->updateDetails(
            $employee,
            [
                'uuid' => $freeUuid,
                'first_name' => 'Local',
                'last_name' => 'Draft',
                'tax_id' => '1111111111',
            ],
            [],
            [],
        );

        $employee->refresh();
        $localParty->refresh();

        $this->assertSame($localParty->id, $employee->partyId);
        $this->assertSame($freeUuid, $localParty->uuid);
    }

    #[Test]
    public function update_details_updates_party_in_place_when_already_linked(): void
    {
        [, $employee, , $canonicalParty] = $this->makeConflictingParties();
        $employee->party()->associate($canonicalParty)->save();

        Repository::employee()->updateDetails(
            $employee,
            [
                'uuid' => $canonicalParty->uuid,
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'tax_id' => '2589631475',
            ],
            [],
            [],
        );

        $employee->refresh();
        $canonicalParty->refresh();

        $this->assertSame($canonicalParty->id, $employee->partyId);
        $this->assertSame('Updated', $canonicalParty->firstName);
    }

    /**
     * @return array{0: LegalEntity, 1: Employee, 2: Party, 3?: Party}
     */
    private function makeConflictingParties(bool $withCanonical = true): array
    {
        $typeId = DB::table('legal_entity_types')->where('name', 'PRIMARY_CARE')->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => 'PRIMARY_CARE']);

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
}
