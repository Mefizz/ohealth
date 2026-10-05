<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Classes\eHealth\Api\EmployeeApi;
use App\Enums\Employee\RequestStatus;
use App\Enums\Status;
use App\Enums\User\Role;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use App\Models\Permission;
use App\Models\Relations\Party;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SyncUserEmployeesAndRolesTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function two_users_in_same_party_receive_only_their_own_employee_roles(): void
    {
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();

        $ownerUser = $this->createUser($party, 'owner@example.com', '2026-07-16 23:59:40');
        $hrUser = $this->createUser($party, 'hr@example.com', '2026-07-10 10:00:00');

        $ownerEmployee = $this->createEmployee($legalEntity, $party, Role::OWNER->value, 'P2', $ownerUser->id, '2026-08-05 12:00:00');
        $hrEmployee = $this->createEmployee($legalEntity, $party, Role::HR->value, 'P14', $hrUser->id, '2026-07-01 12:00:00');

        EmployeeRequest::create([
            'uuid' => (string) Str::uuid(),
            'legal_entity_id' => $legalEntity->id,
            'status' => RequestStatus::APPROVED->value,
            'position' => 'P14',
            'start_date' => $hrEmployee->getRawOriginal('start_date'),
            'employee_type' => Role::HR->value,
            'email' => $hrUser->email,
            'party_id' => $party->id,
            'employee_id' => $hrEmployee->id,
            'applied_at' => '2026-07-01 12:00:00',
        ]);

        setPermissionsTeamId($legalEntity->id);

        Repository::party()->syncUserEmployeesAndRoles($party->fresh(), $legalEntity->fresh());

        $ownerUser->unsetRelation('roles');
        $hrUser->unsetRelation('roles');

        $this->assertTrue($ownerUser->hasRole(Role::OWNER->value));
        $this->assertFalse($ownerUser->hasRole(Role::HR->value));
        $this->assertTrue($hrUser->hasRole(Role::HR->value));
        $this->assertFalse($hrUser->hasRole(Role::OWNER->value));

        $this->assertDatabaseHas('employee_users', [
            'employee_id' => $ownerEmployee->id,
            'user_id' => $ownerUser->id,
        ]);
        $this->assertDatabaseHas('employee_users', [
            'employee_id' => $hrEmployee->id,
            'user_id' => $hrUser->id,
        ]);
        $this->assertDatabaseMissing('employee_users', [
            'employee_id' => $hrEmployee->id,
            'user_id' => $ownerUser->id,
        ]);
    }

    #[Test]
    public function unbound_party_specialist_does_not_grant_role_or_survive_resync(): void
    {
        // Reproduces +outp35 lockout: OWNER bound to user, SPECIALIST on same party with null user_id.
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();
        $ownerUser = $this->createUser($party, 'outp35@example.com', '2026-07-16 23:59:40');

        $this->createEmployee($legalEntity, $party, Role::OWNER->value, 'P2', $ownerUser->id, '2026-08-05 12:00:00');
        $this->createEmployee($legalEntity, $party, Role::SPECIALIST->value, 'P56', null, '2026-06-01 10:00:00');

        setPermissionsTeamId($legalEntity->id);
        Auth::shouldUse('ehealth');

        // Simulate previously invented party-wide SPECIALIST role.
        $ownerUser->assignRole(Role::OWNER->value);
        $ownerUser->assignRole(Role::SPECIALIST->value);
        $this->assertTrue($ownerUser->hasRole(Role::SPECIALIST->value));

        Repository::party()->syncUserEmployeesAndRoles($party->fresh(), $legalEntity->fresh());

        $ownerUser->unsetRelation('roles');
        $this->assertTrue($ownerUser->hasRole(Role::OWNER->value));
        $this->assertFalse(
            $ownerUser->hasRole(Role::SPECIALIST->value),
            'SPECIALIST without user binding must be removed so oauth authorize does not 422'
        );
    }

    #[Test]
    public function employee_linked_only_via_request_employee_id_grants_role(): void
    {
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();
        $user = $this->createUser($party, 'doctor@example.com', '2026-07-16 23:59:40');

        $employee = $this->createEmployee(
            $legalEntity,
            $party,
            Role::SPECIALIST->value,
            'P56',
            null,
            '2026-06-01 10:00:00'
        );

        EmployeeRequest::create([
            'uuid' => (string) Str::uuid(),
            'legal_entity_id' => $legalEntity->id,
            'status' => RequestStatus::APPROVED->value,
            'position' => 'P56',
            'start_date' => $employee->getRawOriginal('start_date'),
            'employee_type' => Role::SPECIALIST->value,
            'email' => $user->email,
            'party_id' => $party->id,
            'employee_id' => $employee->id,
            'applied_at' => '2026-06-01 10:00:00',
        ]);

        setPermissionsTeamId($legalEntity->id);

        Repository::party()->syncUserEmployeesAndRoles($party->fresh(), $legalEntity->fresh());

        $user->unsetRelation('roles');

        $this->assertTrue($user->hasRole(Role::SPECIALIST->value));
        $this->assertDatabaseHas('employee_users', [
            'employee_id' => $employee->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function poisoned_pivot_without_user_id_or_request_link_does_not_keep_role(): void
    {
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();
        $ownerUser = $this->createUser($party, 'outp35@example.com', '2026-07-16 23:59:40');

        $this->createEmployee($legalEntity, $party, Role::OWNER->value, 'P2', $ownerUser->id, '2026-08-05 12:00:00');
        $specialist = $this->createEmployee($legalEntity, $party, Role::SPECIALIST->value, 'P56', null, '2026-06-01 10:00:00');

        // Legacy party-sharing left a pivot row without employees.user_id.
        DB::table('employee_users')->insert([
            'employee_id' => $specialist->id,
            'user_id' => $ownerUser->id,
        ]);

        setPermissionsTeamId($legalEntity->id);
        Auth::shouldUse('ehealth');
        $ownerUser->assignRole(Role::OWNER->value);
        $ownerUser->assignRole(Role::SPECIALIST->value);

        Repository::party()->syncUserEmployeesAndRoles($party->fresh(), $legalEntity->fresh());

        $ownerUser->unsetRelation('roles');
        $this->assertTrue($ownerUser->hasRole(Role::OWNER->value));
        $this->assertFalse($ownerUser->hasRole(Role::SPECIALIST->value));
        $this->assertDatabaseMissing('employee_users', [
            'employee_id' => $specialist->id,
            'user_id' => $ownerUser->id,
        ]);
    }

    #[Test]
    public function logined_role_without_matching_employee_does_not_throw(): void
    {
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();
        $user = $this->createUser($party, 'owner@example.com', '2026-07-16 23:59:40');

        $this->createEmployee($legalEntity, $party, Role::OWNER->value, 'P2', $user->id, '2026-08-05 12:00:00');

        setPermissionsTeamId($legalEntity->id);
        $this->actingAs($user);
        Session::put('first_login_role', Role::HR->value);

        Repository::party()->syncUserEmployeesAndRoles($party->fresh(), $legalEntity->fresh());

        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(Role::OWNER->value));
        $this->assertFalse($user->hasRole(Role::HR->value));
    }

    #[Test]
    public function scope_rejection_is_detected_and_last_granted_scopes_are_read(): void
    {
        $legalEntity = $this->createLegalEntity();
        $party = $this->createParty();
        $user = $this->createUser($party, 'owner@example.com', '2026-07-16 23:59:40');

        setPermissionsTeamId($legalEntity->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::shouldUse('ehealth');

        $divisionRead = Permission::findOrCreate('division:read', 'ehealth');
        $employeeRead = Permission::findOrCreate('employee:read', 'ehealth');

        $ownerRole = \App\Models\Role::findByName(Role::OWNER->value, 'ehealth');
        $ownerRole->givePermissionTo($divisionRead);
        $ownerRole->givePermissionTo($employeeRead);
        $user->assignRole($ownerRole);

        $user->givePermissionTo($divisionRead);
        $user->givePermissionTo($employeeRead);

        $isScopeRejection = new ReflectionMethod(EmployeeApi::class, 'isScopeRejection');
        $lastGrantedScope = new ReflectionMethod(EmployeeApi::class, 'lastGrantedScope');

        $scopeError = new EHealthValidationException([
            'error' => ['message' => 'User requested scope that is not allowed by role based access policies.'],
        ]);
        $otherError = new EHealthValidationException([
            'error' => ['message' => 'Employee not found'],
        ]);

        $this->assertTrue($isScopeRejection->invoke(null, $scopeError));
        $this->assertFalse($isScopeRejection->invoke(null, $otherError));

        $granted = $lastGrantedScope->invoke(null, $user->fresh());

        $this->assertStringContainsString('division:read', $granted);
        $this->assertStringContainsString('employee:read', $granted);
        $this->assertSame('', $lastGrantedScope->invoke(null, null));
    }

    private function createLegalEntity(string $typeName = 'OUTPATIENT'): LegalEntity
    {
        $typeId = DB::table('legal_entity_types')->where('name', $typeName)->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => $typeName]);

        return LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'sync_status' => 'COMPLETED',
            'legal_entity_type_id' => $typeId,
            'is_active' => true,
        ]);
    }

    private function createParty(): Party
    {
        return Party::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Andrii',
            'last_name' => 'Kopylets',
            'tax_id' => '3461807396',
            'birth_date' => '1990-01-01',
            'gender' => 'MALE',
        ]);
    }

    private function createUser(Party $party, string $email, string $insertedAt): User
    {
        return User::forceCreate([
            'uuid' => (string) Str::uuid(),
            'email' => $email,
            'password' => Hash::make('password'),
            'party_id' => $party->id,
            'inserted_at' => $insertedAt,
            'email_verified_at' => now(),
        ]);
    }

    private function createEmployee(
        LegalEntity $legalEntity,
        Party $party,
        string $employeeType,
        string $position,
        ?int $userId,
        string $insertedAt
    ): Employee {
        return Employee::create([
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Andrii Kopylets',
            'employee_type' => $employeeType,
            'status' => Status::APPROVED->value,
            'legal_entity_id' => $legalEntity->id,
            'is_active' => true,
            'position' => $position,
            'start_date' => '2026-07-01',
            'user_id' => $userId,
            'party_id' => $party->id,
            'inserted_at' => $insertedAt,
        ]);
    }
}
