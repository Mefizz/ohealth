<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Classes\eHealth\Api\EmployeeRequest as EmployeeRequestApi;
use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\EHealthResponse;
use App\Enums\Employee\RevisionStatus;
use App\Events\EHealthUserLogin;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Listeners\eHealth\EmployeeCreate;
use App\Jobs\CompleteSync;
use App\Jobs\EmployeeRequestDetailsUpsert;
use App\Jobs\EmployeeRequestsSyncAll;
use App\Jobs\EmployeeSync;
use App\Livewire\Employee\EmployeeEdit;
use App\Livewire\Employee\Forms\EmployeeForm;
use App\Livewire\Party\PartyEdit;
use App\Models\Division;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use App\Models\Relations\Party;
use App\Models\Revision;
use App\Models\User;
use App\Policies\EmployeeRequestPolicy;
use App\Repositories\Repository;
use App\Services\Employee\EmployeeLegalEntityGuard;
use App\Services\Employee\EmployeeRequestProcessor;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use UnexpectedValueException;

class EmployeeLegalEntityIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function entity(): LegalEntity
    {
        $type = DB::table('legal_entity_types')->where('name', 'PRIMARY_CARE')->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => 'PRIMARY_CARE']);

        return LegalEntity::create([
            'uuid' => (string) Str::uuid(), 'edrpou' => '1234567890',
            'legal_entity_type_id' => $type, 'status' => 'ACTIVE',
            'sync_status' => 'COMPLETED', 'is_active' => true,
        ]);
    }

    private function party(): Party
    {
        return Party::create([
            'uuid' => (string) Str::uuid(), 'first_name' => 'Synthetic', 'last_name' => 'Person',
            'birth_date' => '1990-01-01', 'gender' => 'FEMALE', 'tax_id' => '1234567890',
        ]);
    }

    private function employee(LegalEntity $entity, ?Party $party = null, array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'uuid' => (string) Str::uuid(), 'legal_entity_id' => $entity->id,
            'legal_entity_uuid' => $entity->uuid, 'party_id' => ($party ?? $this->party())->id,
            'status' => 'APPROVED', 'is_active' => true, 'position' => 'P1',
            'employee_type' => 'DOCTOR', 'start_date' => '2026-01-01',
        ], $overrides));
    }

    private function request(LegalEntity $entity, array $overrides = []): EmployeeRequest
    {
        return EmployeeRequest::create(array_merge([
            'uuid' => (string) Str::uuid(), 'legal_entity_id' => $entity->id,
            'legal_entity_uuid' => $entity->uuid, 'status' => 'NEW', 'position' => 'P1',
            'employee_type' => 'DOCTOR', 'start_date' => '2026-01-01',
        ], $overrides));
    }

    private function division(LegalEntity $entity): Division
    {
        return Division::create([
            'uuid' => (string) Str::uuid(), 'legal_entity_id' => $entity->id,
            'name' => 'Synthetic division', 'email' => 'division@example.invalid',
            'mountain_group' => false, 'is_active' => true, 'status' => 'ACTIVE',
        ]);
    }

    private function details(LegalEntity $entity, string $uuid, array $overrides = []): EHealthResponse
    {
        $raw = array_merge([
            'id' => $uuid, 'legal_entity_id' => $entity->uuid, 'division_id' => null,
            'employee_id' => null, 'status' => 'NEW', 'position' => 'P1', 'employee_type' => 'DOCTOR',
            'start_date' => '2026-01-01', 'inserted_at' => '2026-01-01T10:00:00Z',
            'updated_at' => '2026-01-01T10:00:00Z',
            'party' => ['email' => 'synthetic@example.invalid', 'first_name' => 'Synthetic',
                'last_name' => 'Person', 'gender' => 'FEMALE', 'birth_date' => '1990-01-01',
                'tax_id' => '1234567890', 'documents' => [], 'phones' => []],
        ], $overrides);
        $api = app(EmployeeRequestApi::class);

        return new EHealthResponse(
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $raw])),
            fn ($response) => (new ReflectionMethod($api, 'validate'))->invoke($api, $response),
            $api->mapRequestCreate(...),
        );
    }

    private function process(object $job, EHealthResponse $response): void
    {
        (new ReflectionMethod($job, 'processResponse'))->invoke($job, $response);
    }

    public function test_list_with_same_edrpou_does_not_persist_unverified_requests(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $uuid = (string) Str::uuid();
        $result = app(EmployeeRequestProcessor::class)->processBatch([
            ['uuid' => $uuid], ['uuid' => $uuid], ['uuid' => (string) Str::uuid(), 'legal_entity_uuid' => $a->uuid],
        ], $b);
        $this->assertSame($a->edrpou, $b->edrpou);
        $this->assertSame([$uuid], $result);
        $this->assertDatabaseCount('employee_requests', 0);
    }

    public static function statuses(): array
    {
        return [['NEW'], ['APPROVED'], ['REJECTED'], ['EXPIRED']];
    }

    #[DataProvider('statuses')]
    public function test_foreign_details_never_create_request_or_revision(string $status): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $uuid = (string) Str::uuid();
        $this->process(new EmployeeRequestDetailsUpsert($uuid, $b), $this->details($a, $uuid, ['status' => $status]));
        $this->assertDatabaseCount('employee_requests', 0);
        $this->assertDatabaseCount('revisions', 0);
    }

    public function test_own_details_are_imported_with_consistent_identity(): void
    {
        $entity = $this->entity();
        $uuid = (string) Str::uuid();
        $this->process(new EmployeeRequestDetailsUpsert($uuid, $entity), $this->details($entity, $uuid));
        $request = EmployeeRequest::forLegalEntity($entity)->sole();
        $this->assertSame($uuid, $request->uuid);
        $this->assertSame($entity->uuid, $request->legal_entity_uuid);
        $this->assertSame($entity->uuid, data_get($request->revision->ehealth_response, 'data.legal_entity_id'));
        $this->assertSame(RevisionStatus::PENDING, $request->revision->status);
        $this->assertNull($request->applied_at);
    }

    public function test_foreign_request_with_existing_uuid_is_not_copied_or_changed(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $request = $this->request($a);
        $this->process(new EmployeeRequestDetailsUpsert($request->uuid, $b), $this->details($a, $request->uuid));
        $this->assertDatabaseCount('employee_requests', 1);
        $this->assertSame($a->id, $request->fresh()->legal_entity_id);
        $this->assertFalse(EmployeeRequest::forLegalEntity($b)->exists());
    }

    public function test_response_uuid_must_match_requested_uuid(): void
    {
        $entity = $this->entity();
        $this->process(new EmployeeRequestDetailsUpsert((string) Str::uuid(), $entity), $this->details($entity, (string) Str::uuid()));
        $this->assertDatabaseCount('employee_requests', 0);
    }

    public function test_missing_remote_entity_fails_closed(): void
    {
        $this->expectException(UnexpectedValueException::class);
        app(EmployeeLegalEntityGuard::class)->assertRemote(['id' => (string) Str::uuid()], $this->entity());
    }

    public function test_api_uses_the_guard_bound_in_the_container(): void
    {
        $entity = $this->entity();
        $data = ['legal_entity_uuid' => $entity->uuid];
        $guard = Mockery::mock(EmployeeLegalEntityGuard::class);
        $guard->shouldReceive('assertRemote')->once()->with($data, $entity)
            ->andThrow(new UnexpectedValueException('Rejected by the injected guard.'));
        $this->instance(EmployeeLegalEntityGuard::class, $guard);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Rejected by the injected guard.');
        EHealth::employeeRequest()->mapRequestCreate($data, $entity);
    }

    public function test_http_details_mapping_preserves_current_division_and_party_filters(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $party = $this->party();
        $otherParty = $this->party();
        $division = $this->division($b);
        $own = $this->request($b, ['party_id' => $party->id]);
        $this->request($a, ['party_id' => $party->id]);
        $this->request($b, ['party_id' => $otherParty->id]);
        config()->set('ehealth.api.domain', 'https://ehealth.example.invalid');
        session()->put(config('ehealth.api.oauth.bearer_token'), 'synthetic-token');
        $raw = $this->details($b, $own->uuid, ['division_id' => $division->uuid])->getData();
        Http::fake();
        $api = EHealth::employeeRequest()
            ->stub([fn () => Http::response(['data' => $raw])])
            ->preventStrayRequests();
        $response = $api->getDetails($own->uuid);
        $mapped = $response->map($response->validate(), $b, null, $party->id);

        $this->assertSame($b->id, $mapped['legal_entity_id']);
        $this->assertSame($division->id, $mapped['division_id']);
        $this->assertSame($division->id, $api->mapRevisionData($response, $b)['employee_request_data']['division_id']);
        $this->assertSame($own->id, EmployeeRequest::forLegalEntity($b)->wherePartyId($party->id)->sole()->id);
        $this->assertSame($b->id, $own->legalEntityId);
        $this->assertSame($party->id, $own->partyId);
        Http::assertSent(fn ($request) => $request->url() === 'https://ehealth.example.invalid/api/employee_requests/' . $own->uuid
            && $request->hasHeader('Authorization', 'Bearer synthetic-token'));
        Http::assertSentCount(1);
    }

    public function test_foreign_employee_or_division_in_details_is_rejected(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $employee = $this->employee($a);
        $division = $this->division($a);
        foreach ([['employee_id' => $employee->uuid], ['division_id' => $division->uuid]] as $overrides) {
            $uuid = (string) Str::uuid();
            $this->process(new EmployeeRequestDetailsUpsert($uuid, $b), $this->details($b, $uuid, $overrides));
        }
        $this->assertDatabaseCount('employee_requests', 0);
    }

    public function test_legacy_cross_links_are_hidden_and_cannot_be_imported_over(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $employee = $this->employee($a);
        $division = $this->division($a);
        $requests = [
            $this->request($b, ['legal_entity_uuid' => $a->uuid]),
            $this->request($b, ['employee_id' => $employee->id]),
            $this->request($b, ['division_id' => $division->id]),
            $this->request($b),
        ];
        $requests[3]->revision()->save(new Revision([
            'status' => RevisionStatus::PENDING, 'data' => [],
            'ehealth_response' => ['data' => ['legal_entity_id' => $a->uuid]],
        ]));
        foreach ($requests as $request) {
            $this->assertFalse($request->belongsToLegalEntity($b));
            $this->process(new EmployeeRequestDetailsUpsert($request, $b), $this->details($b, $request->uuid));
            $this->assertNull($request->fresh()->sync_status);
        }
        $this->assertFalse(EmployeeRequest::forLegalEntity($b)->exists());
    }

    public function test_repository_rejects_foreign_employee_and_division(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $employee = $this->employee($a);
        $division = $this->division($a);
        foreach ([['employee_id' => $employee->id], ['division_id' => $division->id]] as $data) {
            try {
                Repository::employee()->createEmployeeRequestDraft($data, $b);
                $this->fail('Foreign reference was accepted.');
            } catch (UnexpectedValueException) {
                $this->assertDatabaseCount('employee_requests', 0);
            }
        }
        $this->expectException(UnexpectedValueException::class);
        Repository::employee()->createEmployeeRequestDraft([], $b, $employee);
    }

    public function test_repository_accepts_current_employee(): void
    {
        $entity = $this->entity();
        $employee = $this->employee($entity);
        $request = Repository::employee()->createEmployeeRequestDraft(['position' => 'P1', 'employee_type' => 'DOCTOR'], $entity, $employee);
        $this->assertTrue($request->belongsToLegalEntity($entity));
        $this->assertSame($employee->id, $request->employee_id);
    }

    public function test_all_page_candidates_are_verified_before_next_page(): void
    {
        $entity = $this->entity();
        $job = new EmployeeRequestsSyncAll($entity);
        $uuids = [(string) Str::uuid(), (string) Str::uuid()];
        (new ReflectionProperty($job, 'requestUuids'))->setValue($job, $uuids);
        $chain = (new ReflectionMethod($job, 'getNextPageJob'))->invoke($job);
        foreach ($uuids as $uuid) {
            $this->assertInstanceOf(EmployeeRequestDetailsUpsert::class, $chain);
            $this->assertSame($uuid, $chain->employeeRequest);
            $chain = (new ReflectionProperty($chain, 'nextEntity'))->getValue($chain);
        }
        $this->assertInstanceOf(EmployeeRequestsSyncAll::class, $chain);
        $this->assertSame(2, (new ReflectionProperty($chain, 'page'))->getValue($chain));
        $terminal = $job->getRequestDetailsChain(new CompleteSync($entity), [$uuids[0]]);
        $this->assertInstanceOf(CompleteSync::class, (new ReflectionProperty($terminal, 'nextEntity'))->getValue($terminal));
    }

    public function test_employee_sync_cannot_reassign_existing_employee_to_another_entity(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $employee = $this->employee($a);
        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('json')->with('data')->andReturn([]);
        $response->shouldReceive('validate')->andReturn([['uuid' => $employee->uuid, 'status' => 'APPROVED']]);
        try {
            $this->process(new EmployeeSync($b), $response);
            $this->fail('Foreign employee was reassigned.');
        } catch (UnexpectedValueException) {
            $this->assertSame($a->id, $employee->fresh()->legal_entity_id);
        }
    }

    public function test_party_fallback_uses_only_current_entity_documents_and_phones(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $party = $this->party();
        $this->instance('legalEntity', $b);
        foreach ([[$b, 'OWN', '2026-01-01'], [$a, 'FOREIGN', '2026-02-01']] as [$entity, $number, $created]) {
            $request = $this->request($entity, ['party_id' => $party->id, 'created_at' => $created]);
            $request->revision()->save(new Revision(['status' => RevisionStatus::PENDING, 'data' => [
                'documents' => [['type' => 'PASSPORT', 'number' => $number]],
                'phones' => [['type' => 'MOBILE', 'number' => $number]],
            ]]));
        }
        $form = new EmployeeForm(new class extends Component
        {}, 'form');
        $form->hydrate($party);
        $this->assertSame('OWN', $form->documents[0]['number']);
        $this->assertSame('OWN', $form->party['phones'][0]['number']);
    }

    public function test_party_edit_keeps_current_active_employee_despite_newer_foreign_or_stopped_records(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $party = $this->party();
        $own = $this->employee($b, $party);
        $this->employee($a, $party, ['start_date' => '2026-09-01', 'employee_type' => 'OWNER']);
        $this->employee($b, $party, ['start_date' => '2026-10-01', 'status' => 'STOPPED', 'is_active' => false]);
        $this->instance('legalEntity', $b);
        $component = Mockery::mock(PartyEdit::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $component->shouldReceive('loadDictionaries', 'loadDivisions');
        $component->form = new EmployeeForm($component, 'form');
        $component->mount($b, $party);
        $request = (new ReflectionMethod($component, 'handleDraftPersistence'))->invoke($component);
        $this->assertSame($own->id, $request->employee_id);
        $this->assertSame($own->uuid, $request->revision->data['employee_uuid']);
        $this->assertTrue($request->belongsToLegalEntity($b));
    }

    public function test_employee_edit_ignores_foreign_draft_and_foreign_owner_lock(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $party = $this->party();
        $own = $this->employee($b, $party);
        $this->employee($a, $party, ['employee_type' => 'OWNER']);
        $this->request($a, ['employee_id' => $own->id, 'uuid' => null, 'party_id' => $party->id]);
        $this->instance('legalEntity', $b);
        $component = Mockery::mock(EmployeeEdit::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $component->shouldReceive('loadDictionaries', 'loadDivisions');
        $component->form = new EmployeeForm($component, 'form');
        $component->mount($b, $own);
        $this->assertNull($component->employeeRequestId);
        $this->assertFalse($component->isPersonalDataLocked);
        $this->assertSame('P1', $component->form->position);
    }

    public function test_elevated_role_cannot_view_update_or_delete_foreign_request(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $this->instance('legalEntity', $b);
        $request = $this->request($a, ['uuid' => null]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can', 'hasElevatedEmployeeRole')->andReturn(true);
        $policy = new EmployeeRequestPolicy();
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertSame(404, $policy->$ability($user, $request)->status());
        }
    }

    public function test_manual_sync_does_not_apply_foreign_rejection(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $request = $this->request($b);
        session()->put(config('ehealth.api.oauth.bearer_token'), 'synthetic-token');
        $response = $this->details($a, $request->uuid, ['status' => 'REJECTED']);
        $api = Mockery::mock(EmployeeRequestApi::class);
        $api->shouldReceive('getDetails')->with($request->uuid)->andReturn($response);
        $this->instance(EmployeeRequestApi::class, $api);
        try {
            app(EmployeeRequestProcessor::class)->syncSinglePendingRequest($request, $b);
            $this->fail('Foreign decision was applied.');
        } catch (UnexpectedValueException) {
            $this->assertSame('NEW', $request->fresh()->status->value);
            $this->assertNull($request->fresh()->applied_at);
        }
    }

    public function test_approved_request_cannot_modify_foreign_employee(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $employee = $this->employee($a);
        $request = $this->request($b);
        $request->revision()->save(new Revision(['status' => RevisionStatus::PENDING, 'data' => [
            'party' => ['tax_id' => '1234567890'], 'employee_request_data' => ['position' => 'P2'],
        ]]));
        try {
            app(EmployeeRequestProcessor::class)->applyApprovedRequest($request, [
                'legal_entity_id' => $b->uuid, 'employee_id' => $employee->uuid, 'status' => 'APPROVED',
            ]);
            $this->fail('Foreign employee was modified.');
        } catch (UnexpectedValueException) {
            $this->assertSame('P1', $employee->fresh()->position);
            $this->assertSame($a->id, $employee->fresh()->legal_entity_id);
            $this->assertSame('NEW', $request->fresh()->status->value);
        }
    }

    public function test_owner_login_ignores_other_entity_requests_with_same_email(): void
    {
        $a = $this->entity();
        $b = $this->entity();
        $user = User::create(['uuid' => (string) Str::uuid(), 'email' => 'synthetic@example.invalid', 'password' => 'synthetic']);
        $request = $this->request($a, ['email' => $user->email, 'employee_type' => 'OWNER']);
        session()->put(config('ehealth.api.oauth.bearer_token'), 'synthetic-token');
        $event = new EHealthUserLogin($user, $b, $user->uuid, []);
        app(EmployeeCreate::class)->handle($event);
        $this->assertNull($user->fresh()->party_id);
        $this->assertSame('NEW', $request->fresh()->status->value);
        Http::assertNothingSent();
    }

    public static function inaccessibleStatuses(): array
    {
        return [[403], [404]];
    }

    #[DataProvider('inaccessibleStatuses')]
    public function test_inaccessible_candidate_does_not_create_a_local_skeleton(int $status): void
    {
        $entity = $this->entity();
        $uuid = (string) Str::uuid();
        $api = Mockery::mock(EmployeeRequestApi::class);
        $api->shouldReceive('withToken')->with('synthetic-token')->andReturnSelf();
        $api->shouldReceive('getDetails')->with($uuid)->andThrow(new EHealthResponseException(
            new \Illuminate\Http\Client\Response(new Response($status, ['Content-Type' => 'application/json'], '{"error":{"message":"Access denied"}}')),
        ));
        $this->instance(EmployeeRequestApi::class, $api);
        $job = new EmployeeRequestDetailsUpsert($uuid, $entity);
        $response = (new ReflectionMethod($job, 'sendRequest'))->invoke($job, 'synthetic-token');
        $this->assertNull($response);
        (new ReflectionMethod($job, 'processResponse'))->invoke($job, $response);
        $this->assertDatabaseCount('employee_requests', 0);
    }

    public function test_details_jobs_queued_with_models_before_the_fix_remain_compatible(): void
    {
        $entity = $this->entity();
        $request = $this->request($entity);
        $job = new EmployeeRequestDetailsUpsert($request, $entity);
        $job->employeeRequest = $request;
        $this->process($job, $this->details($entity, $request->uuid));
        $this->assertSame('COMPLETED', $request->fresh()->sync_status);
        $this->assertDatabaseCount('employee_requests', 1);
    }

    public function test_verified_approval_still_updates_the_current_employee_and_keeps_party_link(): void
    {
        $entity = $this->entity();
        $party = $this->party();
        $employee = $this->employee($entity, $party);
        $request = $this->request($entity, ['employee_id' => $employee->id, 'party_id' => $party->id]);
        $request->revision()->save(new Revision(['status' => RevisionStatus::PENDING, 'data' => [
            'party' => ['uuid' => $party->uuid, 'tax_id' => '1234567890', 'first_name' => 'Synthetic', 'last_name' => 'Person'],
            'employee_request_data' => ['position' => 'P2', 'employee_type' => 'DOCTOR', 'start_date' => '2026-01-01'],
            'documents' => [], 'phones' => [],
        ]]));
        $this->process(new EmployeeRequestDetailsUpsert($request->uuid, $entity), $this->details($entity, $request->uuid, [
            'status' => 'APPROVED', 'employee_id' => $employee->uuid, 'position' => 'P2',
        ]));
        $this->assertSame('P2', $employee->fresh()->position);
        $this->assertSame($entity->id, $employee->fresh()->legal_entity_id);
        $this->assertSame($party->id, $request->fresh()->party_id);
        $this->assertSame('APPROVED', $request->fresh()->status->value);
        $this->assertSame(RevisionStatus::APPLIED, $request->fresh()->revision->status);
    }
}
