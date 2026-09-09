<?php

declare(strict_types=1);

namespace Tests\Feature\Encounter;

use App\Enums\Status;
use App\Enums\User\Role;
use App\Livewire\Encounter\Concerns\ManagesEncounterComposition;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\Person\Person;
use App\Models\Relations\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Composition drawer on the encounter page (МВТН / МВН) — same shell as eRx / referrals.
 */
class EncounterCompositionDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => [
                database_path('migrations'),
                database_path('migrations/install'),
            ],
            '--realpath' => true,
        ]);
    }

    public function test_composition_drawer_opens_for_primary_care_doctor_with_create_scope(): void
    {
        ['person' => $person] = $this->actingPrimaryCareDoctor();

        $harness = new EncounterCompositionHarness();
        $harness->personId = $person->id;
        $harness->prepersonId = null;
        $harness->encounterUuid = (string) Str::uuid();

        $this->assertTrue($harness->mayOpenEncounterCompositionDrawer());

        $harness->openEncounterCompositionDrawer();

        $this->assertTrue($harness->showEncounterCompositionDrawer);
        $this->assertSame('temp_disability', $harness->encounterCompositionKind);
        $this->assertSame(1, $harness->encounterCompositionDrawerKey);
    }

    public function test_composition_drawer_stays_closed_without_create_scope(): void
    {
        ['person' => $person] = $this->actingPrimaryCareDoctor(withCreateScope: false);

        $harness = new EncounterCompositionHarness();
        $harness->personId = $person->id;
        $harness->prepersonId = null;
        $harness->encounterUuid = (string) Str::uuid();

        $this->assertFalse($harness->mayOpenEncounterCompositionDrawer());

        $harness->openEncounterCompositionDrawer();

        $this->assertFalse($harness->showEncounterCompositionDrawer);
        $this->assertTrue(session()->has('error'));
    }

    /**
     * @return array{legalEntity: LegalEntity, person: Person, user: User}
     */
    private function actingPrimaryCareDoctor(bool $withCreateScope = true): array
    {
        $typeId = DB::table('legal_entity_types')->where('name', LegalEntity::TYPE_PRIMARY_CARE)->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => LegalEntity::TYPE_PRIMARY_CARE]);

        $legalEntity = LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'sync_status' => 'COMPLETED',
            'legal_entity_type_id' => $typeId,
            'is_active' => true,
        ]);

        $party = Party::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Ольга',
            'last_name' => 'Лікарівна',
            'tax_id' => '1234567890',
            'birth_date' => '1985-05-05',
            'gender' => 'FEMALE',
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'pmd-doctor@example.com',
            'password' => Hash::make('password'),
            'party_id' => $party->id,
        ]);

        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Ольга Лікарівна',
            'employee_type' => Role::DOCTOR->value,
            'status' => Status::APPROVED->value,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'position' => 'P1',
            'start_date' => now()->format('Y-m-d'),
            'user_id' => $user->id,
            'party_id' => $party->id,
        ]);

        $user->employees()->attach($employee->id);

        $person = Person::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Пацієнт',
            'last_name' => 'Якийсь',
            'birth_date' => '2001-02-23',
            'gender' => 'MALE',
        ]);

        $this->instance('legalEntity', $legalEntity);

        if (config('permission.teams')) {
            setPermissionsTeamId($legalEntity->id);
        }

        if ($withCreateScope) {
            $user->givePermissionToParent(
                Permission::findOrCreate('composition:create', 'web'),
            );
        }

        $this->actingAs($user);

        return compact('legalEntity', 'person', 'user');
    }
}

/**
 * Lightweight host for the composition drawer trait (avoids full EncounterEdit mount).
 */
class EncounterCompositionHarness
{
    use ManagesEncounterComposition;

    public ?int $personId = null;

    public ?int $prepersonId = null;

    public string $encounterUuid = '';
}
