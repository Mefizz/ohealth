<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Enums\Employee\RequestStatus;
use App\Classes\eHealth\Api\Employee as EmployeeApi;
use App\Classes\eHealth\EHealthResponse;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Enums\Employee\RevisionStatus;
use App\Jobs\ClearEmployeeSpecialities;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeRequest;
use App\Models\LegalEntity;
use App\Models\LegalEntityType;
use App\Models\Relations\Party;
use App\Models\User;
use App\Repositories\EmployeeRepository;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClearEmployeeSpecialitiesTest extends TestCase
{
    use DatabaseTransactions;

    private EmployeeRepository $repository;
    private LegalEntity $legalEntity;
    private User $operator;
    private Employee $employee;
    private EmployeeRequest $request;
    private EmployeeApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ehealth.api.domain' => 'https://ehealth.test', 'queue.default' => 'database']);
        Http::preventStrayRequests();
        $this->api = Mockery::mock(EmployeeApi::class);
        $this->api->shouldReceive('withToken')->with('test-operator-token')->andReturnSelf();
        $this->instance(EmployeeApi::class, $this->api);
        $this->repository = new EmployeeRepository();
        $type = LegalEntityType::firstOrCreate(['name' => 'OUTPATIENT']);
        $this->legalEntity = LegalEntity::create(['uuid' => (string) Str::uuid(), 'legal_entity_type_id' => $type->id]);
        $party = Party::create(['uuid' => (string) Str::uuid(), 'first_name' => 'Test', 'last_name' => 'Repair', 'tax_id' => '1234567890']);
        $this->operator = User::create(['email' => Str::uuid().'@example.test', 'password' => bcrypt('test')]);
        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $this->operator->id,
            'payload' => base64_encode(serialize(['auth_token' => 'test-operator-token',
                'ehealth_legal_entity_uuid' => $this->legalEntity->uuid, 'auth_token_expires_at' => now()->addHour()->timestamp,
                'auth_token_scopes' => ['employee:details']])), 'last_activity' => now()->timestamp,
        ]);
        $this->employee = Employee::create([
            'uuid' => (string) Str::uuid(), 'legal_entity_id' => $this->legalEntity->id, 'party_id' => $party->id,
            'employee_type' => 'SPECIALIST', 'position' => 'P8', 'start_date' => '2024-01-01', 'status' => 'APPROVED',
        ]);
        foreach (['THERAPIST', 'NURSING'] as $code) {
            $this->employee->specialities()->create($this->certificate($code));
        }
        $this->request = EmployeeRequest::create([
            'uuid' => (string) Str::uuid(), 'employee_id' => $this->employee->id, 'legal_entity_id' => $this->legalEntity->id,
            'party_id' => $party->id, 'employee_type' => 'SPECIALIST', 'position' => 'P8', 'status' => RequestStatus::APPROVED,
        ]);
        $this->request->revision()->create([
            'status' => RevisionStatus::APPLIED, 'ehealth_response' => ['data' => ['id' => $this->request->uuid]],
            'data' => ['party' => ['tax_id' => $party->taxId],
                'employee_request_data' => ['employee_type' => 'SPECIALIST', 'position' => 'P8', 'start_date' => '2024-01-01'],
                'doctor' => ['specialities' => [$this->certificate('THERAPIST')]]],
        ]);
    }

    public static function modes(): array
    {
        return ['audit by default' => [false], 'explicit apply' => [true]];
    }

    #[DataProvider('modes')]
    public function test_command_queues_all_positions_without_a_manual_uuid_list_and_defaults_to_audit(bool $apply): void
    {
        Bus::fake();
        $imported = $this->employee->replicate();
        $imported->uuid = (string) Str::uuid();
        $imported->position = 'P3';
        $imported->save();
        $imported->specialities()->create($this->certificate('NURSING'));
        $this->artisan('specialities_clear', $apply ? ['--apply' => true] : [])
            ->expectsOutputToContain($apply ? 'Mode: APPLY' : 'Mode: AUDIT')->assertSuccessful();
        Bus::assertBatched(fn ($batch) => count($batch->jobs) === 2
            && $batch->jobs[0]->employeeId === $this->employee->id && $batch->jobs[0]->apply === $apply
            && $batch->jobs[1]->employeeId === $imported->id && $batch->options['queue'] === 'sync');
        Http::assertNothingSent();
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_apply_removes_only_confirmed_extra_rows_and_is_idempotent(): void
    {
        $other = $this->employee->replicate();
        $other->uuid = (string) Str::uuid();
        $other->employeeType = 'ASSISTANT';
        $other->position = 'P3';
        $other->save();
        $other->specialities()->create($this->certificate('NURSING'));
        $revision = $this->request->revision->getAttributes();
        $this->fakeDetails($this->remote(['THERAPIST']), $this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
        $this->assertSame(['NURSING'], $other->specialities()->pluck('speciality')->all());
        $this->assertSame($revision, $this->request->revision->fresh()->getAttributes());
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_deleted_request_history_does_not_prevent_exact_employee_reconciliation(): void
    {
        $this->request->revision->delete();
        $this->request->delete();
        $this->fakeDetails($this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_queue_serialization_preserves_employee_identity_without_exposing_the_token(): void
    {
        $payload = serialize($this->job());
        $this->assertStringNotContainsString('test-operator-token', $payload);
        $this->fakeDetails($this->remote(['THERAPIST']));
        unserialize($payload)->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_async_queue_is_required_to_prevent_an_unthrottled_cli_loop(): void
    {
        Bus::fake();
        config(['queue.default' => 'sync']);
        $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id])
            ->assertExitCode(2);
        Bus::assertNothingBatched();
    }

    public function test_missing_operator_token_does_not_queue_jobs(): void
    {
        Bus::fake();
        DB::table('sessions')->where('user_id', $this->operator->id)->delete();
        $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id])
            ->expectsOutputToContain('no unexpired eHealth session')->assertExitCode(1);
        Bus::assertNothingBatched();
    }

    public function test_normal_sync_session_reader_is_preserved_and_cleanup_resolves_its_entity_context(): void
    {
        $normalSync = new \App\Jobs\EmployeeDetailsUpsert($this->employee, $this->legalEntity);
        $normalSync->user = $this->operator;
        $token = (new \ReflectionMethod($normalSync, 'getToken'))->invoke($normalSync);
        $this->assertSame('test-operator-token', $token);
        $this->assertSame($token, app(UserRepository::class)->getEHealthTokenFromSession($this->operator));
        $this->assertSame($token, app(UserRepository::class)->getSpecialityClearSessions()[$this->legalEntity->uuid]['token']);
        DB::table('sessions')->where('user_id', $this->operator->id)->update(['payload' => base64_encode(serialize([]))]);
        $this->assertSame('', (new \ReflectionMethod($normalSync, 'getToken'))->invoke($normalSync));
        DB::table('sessions')->where('user_id', $this->operator->id)->delete();
        $this->assertSame('', (new \ReflectionMethod($normalSync, 'getToken'))->invoke($normalSync));
    }

    public function test_audit_and_centrally_registered_additional_codes_are_preserved(): void
    {
        $this->fakeDetails($this->remote(['THERAPIST']), $this->remote(['THERAPIST', 'NURSING']));
        $this->job(false)->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
        $this->job()->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_employee_uuid_change_after_queueing_is_skipped_without_a_request(): void
    {
        $job = $this->job();
        $this->employee->update(['uuid' => (string) Str::uuid()]);
        $job->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
        Http::assertNothingSent();
    }

    public function test_wrong_historical_request_link_does_not_select_another_employee(): void
    {
        $imported = $this->employee->replicate();
        $imported->uuid = (string) Str::uuid();
        $imported->save();
        foreach (['THERAPIST', 'NURSING'] as $code) {
            $imported->specialities()->create($this->certificate($code));
        }
        $this->request->update(['employee_id' => $imported->id]);
        $this->fakeDetails($this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
        $this->assertSame(['THERAPIST', 'NURSING'], $imported->specialities()->pluck('speciality')->all());
    }

    public static function requestHistories(): array
    {
        return ['imported' => ['imported'], 'wrong submit ID' => ['response'], 'edit request' => ['edit'], 'wrong party' => ['party']];
    }

    #[DataProvider('requestHistories')]
    public function test_cleanup_uses_current_ehealth_instead_of_unreliable_request_history(string $case): void
    {
        $revision = $this->request->revision;
        if ($case === 'imported' || $case === 'response') {
            $revision->ehealthResponse = $case === 'imported' ? null : ['data' => ['id' => (string) Str::uuid()]];
            $revision->save();
        } else {
            $data = $revision->data;
            if ($case === 'edit') {
                $data['employee_uuid'] = $this->employee->uuid;
            } else {
                $data['party']['tax_id'] = '9999999999';
            }
            $revision->update(['data' => $data]);
        }
        $this->fakeDetails($this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_pending_edit_is_not_overwritten(): void
    {
        EmployeeRequest::create(['employee_id' => $this->employee->id, 'legal_entity_id' => $this->legalEntity->id, 'status' => RequestStatus::SIGNED]);
        $this->job()->handle($this->repository);
        Http::assertNothingSent();
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_whole_database_uses_each_entity_session_and_reports_entities_without_access(): void
    {
        Bus::fake();
        $otherEntity = LegalEntity::create(['uuid' => (string) Str::uuid(), 'legal_entity_type_id' => $this->legalEntity->legalEntityTypeId]);
        $otherOperator = User::create(['email' => Str::uuid().'@example.test', 'password' => bcrypt('test')]);
        $this->storeSession($otherOperator, $otherEntity, 'other-entity-token');
        $other = $this->employee->replicate();
        $other->uuid = (string) Str::uuid();
        $other->legalEntityId = $otherEntity->id;
        $other->save();
        $other->specialities()->create($this->certificate('NURSING'));
        $inaccessibleEntity = LegalEntity::create(['uuid' => (string) Str::uuid(), 'legal_entity_type_id' => $this->legalEntity->legalEntityTypeId]);
        $inaccessible = $other->replicate();
        $inaccessible->uuid = (string) Str::uuid();
        $inaccessible->legalEntityId = $inaccessibleEntity->id;
        $inaccessible->save();
        $inaccessible->specialities()->create($this->certificate('NURSING'));

        $this->artisan('specialities_clear', ['--apply' => true])
            ->expectsOutputToContain('Queued 2 employees. Mode: APPLY')
            ->expectsOutputToContain('local legal entity '.$inaccessibleEntity->id.': no unexpired eHealth session')
            ->assertExitCode(1);
        Bus::assertBatched(fn ($batch) => count($batch->jobs) === 2
            && $batch->jobs[0]->user->id === $this->operator->id && $batch->jobs[1]->user->id === $otherOperator->id);
        $this->fakeDetails($this->remote(['THERAPIST']));
        $otherRemote = $this->remote([]);
        $otherRemote['id'] = $other->uuid;
        $otherRemote['legal_entity']['id'] = $otherEntity->uuid;
        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('getData')->once()->andReturn($otherRemote);
        $this->api->shouldReceive('withToken')->with('other-entity-token')->once()->andReturnSelf();
        $this->api->shouldReceive('getDetails')->with($other->uuid)->once()->andReturn($response);
        foreach (Bus::batched(fn ($batch) => true)->first()->jobs as $job) {
            $job->handle($this->repository);
        }
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
        $this->assertSame(0, $other->specialities()->count());
        $this->assertSame(1, $inaccessible->specialities()->count());
    }

    public function test_bulk_dispatch_is_bounded_and_does_not_require_request_history(): void
    {
        Bus::fake();
        for ($index = 0; $index < 100; $index++) {
            $employee = $this->employee->replicate();
            $employee->uuid = (string) Str::uuid();
            $employee->save();
            $employee->specialities()->create($this->certificate('NURSING'));
        }
        $this->artisan('specialities_clear')->expectsOutputToContain('Queued 101 employees')->assertSuccessful();
        $sizes = Bus::batched(fn ($batch) => true)->map(fn ($batch) => count($batch->jobs))->all();
        $this->assertSame([100, 1], $sizes);
        Http::assertNothingSent();
    }

    public function test_optional_filters_restrict_the_bulk_run(): void
    {
        Bus::fake();
        $other = $this->employee->replicate();
        $other->uuid = (string) Str::uuid();
        $other->save();
        $other->specialities()->create($this->certificate('NURSING'));
        $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id, '--employee' => $other->uuid])
            ->assertSuccessful();
        Bus::assertBatched(fn ($batch) => count($batch->jobs) === 1 && $batch->jobs[0]->employeeId === $other->id);
    }

    public static function unusableSessions(): array
    {
        return ['wrong entity' => ['entity'], 'expired' => ['expired'], 'missing scopes' => ['scope'], 'malformed' => ['malformed']];
    }

    #[DataProvider('unusableSessions')]
    public function test_unusable_sessions_never_authorize_a_bulk_run(string $case): void
    {
        Bus::fake();
        $payload = ['auth_token' => 'test-operator-token', 'ehealth_legal_entity_uuid' => $this->legalEntity->uuid,
            'auth_token_expires_at' => now()->addHour()->timestamp, 'auth_token_scopes' => ['employee:details']];
        if ($case === 'entity') {
            $payload['ehealth_legal_entity_uuid'] = (string) Str::uuid();
        } elseif ($case === 'expired') {
            $payload['auth_token_expires_at'] = now()->subMinute()->timestamp;
        } elseif ($case === 'scope') {
            $payload['auth_token_scopes'] = ['employee:read'];
        }
        DB::table('sessions')->where('user_id', $this->operator->id)->update(['payload' => $case === 'malformed' ? 'invalid-session' : base64_encode(serialize($payload))]);
        $this->artisan('specialities_clear', ['--apply' => true])->assertExitCode(1);
        Bus::assertNothingBatched();
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_latest_valid_session_wins_without_using_an_expired_or_foreign_token(): void
    {
        $this->storeSession($this->operator, $this->legalEntity, 'newest-valid-token', 10);
        $this->storeSession($this->operator, $this->legalEntity, 'expired-token', 20, true);
        $contexts = app(UserRepository::class)->getSpecialityClearSessions($this->operator->id);
        $this->assertSame('newest-valid-token', $contexts[$this->legalEntity->uuid]['token']);
        $this->assertSame([], app(UserRepository::class)->getSpecialityClearSessions($this->operator->id + 100000));
    }

    public function test_queued_job_uses_a_refreshed_session_instead_of_a_token_captured_at_dispatch(): void
    {
        $job = unserialize(serialize($this->job()));
        DB::table('sessions')->where('user_id', $this->operator->id)->delete();
        $this->storeSession($this->operator, $this->legalEntity, 'refreshed-token');
        $this->api->shouldReceive('withToken')->with('refreshed-token')->once()->andReturnSelf();
        $this->fakeDetails($this->remote(['THERAPIST']));
        $job->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_job_waits_for_a_valid_session_and_resumes_after_login(): void
    {
        $job = $this->job()->withFakeQueueInteractions();
        DB::table('sessions')->where('user_id', $this->operator->id)->delete();
        $this->storeSession($this->operator, $this->legalEntity, 'expired-token', expired: true);
        $job->handle($this->repository);
        $job->assertReleased(60);
        $this->assertSame(2, $this->employee->specialities()->count());
        Http::assertNothingSent();
        $this->storeSession($this->operator, $this->legalEntity, 'new-token');
        $this->api->shouldReceive('withToken')->with('new-token')->once()->andReturnSelf();
        $this->fakeDetails($this->remote(['THERAPIST']));
        $job->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_existing_code_with_a_changed_certificate_during_get_is_preserved(): void
    {
        $this->fakeDetails(function () {
            $this->employee->specialities()->whereSpeciality('NURSING')->update(['certificate_number' => 'CHANGED']);

            return $this->remote(['THERAPIST']);
        });
        $this->job()->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
        $this->assertSame('CHANGED', $this->employee->specialities()->whereSpeciality('NURSING')->first()->certificateNumber);
    }

    public function test_explicit_empty_ehealth_list_clears_only_the_target_position(): void
    {
        $other = $this->employee->replicate();
        $other->uuid = (string) Str::uuid();
        $other->employeeType = 'ASSISTANT';
        $other->save();
        $other->specialities()->create($this->certificate('NURSING'));
        $this->fakeDetails($this->remote([]));
        $this->job()->handle($this->repository);
        $this->assertSame(0, $this->employee->specialities()->count());
        $this->assertSame(['NURSING'], $other->specialities()->pluck('speciality')->all());
    }

    public function test_http_error_never_clears_local_specialities(): void
    {
        $response = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(403, [], '{}'));
        $this->api->shouldReceive('getDetails')->with($this->employee->uuid)->once()->andThrow(new EHealthResponseException($response));
        try {
            $this->job()->handle($this->repository);
            $this->fail('A refused API request must not clear data.');
        } catch (EHealthResponseException $exception) {
            $this->assertSame(403, $exception->getCode());
            $this->assertSame(2, $this->employee->specialities()->count());
        }
    }

    public static function invalidResponses(): array
    {
        return ['wrong employee' => ['id'], 'wrong legal entity' => ['legal_entity'], 'wrong party' => ['party'],
            'missing professional block' => ['doctor'], 'missing list' => ['list'], 'ambiguous blocks' => ['ambiguous']];
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_responses_do_not_remove_rows(string $field): void
    {
        $remote = $this->remote(['THERAPIST']);
        if ($field === 'ambiguous') {
            $remote['specialist'] = ['specialities' => []];
        } elseif ($field === 'list') {
            unset($remote['doctor']['specialities']);
        } elseif ($field === 'doctor') {
            unset($remote['doctor']);
        } else {
            $remote[$field] = $field === 'id' ? (string) Str::uuid() : ['id' => (string) Str::uuid()];
        }
        $this->fakeDetails($remote);
        try {
            $this->job()->handle($this->repository);
            $this->fail('An incomplete/foreign response must not clear local data.');
        } catch (ValidationException) {
            $this->assertSame(2, $this->employee->specialities()->count());
        }
    }

    public function test_local_changes_during_http_request_are_not_removed(): void
    {
        $this->fakeDetails(function () {
            $this->employee->specialities()->create($this->certificate('SURGERY'));

            return $this->remote(['THERAPIST']);
        });
        $this->job()->handle($this->repository);
        $this->assertSame(3, $this->employee->specialities()->count());
    }

    public function test_rate_limit_releases_the_job_without_deleting_rows(): void
    {
        $response = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(429, ['Retry-After' => '75'], '{}'));
        $this->api->shouldReceive('getDetails')->with($this->employee->uuid)->once()->andThrow(new EHealthResponseException($response));
        $job = $this->job()->withFakeQueueInteractions();
        $job->handle($this->repository);
        $job->assertReleased(75);
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_cleanup_shares_the_normal_employee_sync_rate_budget(): void
    {
        config(['ehealth.rate_limit.employee' => 1]);
        $normalSync = new \App\Jobs\EmployeeDetailsUpsert($this->employee, $this->legalEntity);
        $normalSync->user = $this->operator;
        $middleware = new \Illuminate\Queue\Middleware\RateLimited('ehealth-employee-get');
        $middleware->handle($normalSync, fn () => true);
        $cleanup = $this->job()->withFakeQueueInteractions();
        $middleware->handle($cleanup, fn () => $cleanup->handle($this->repository));
        $cleanup->assertReleased();
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    private function job(bool $apply = true): ClearEmployeeSpecialities
    {
        return new ClearEmployeeSpecialities($this->employee->id, $this->legalEntity->id, $this->operator, $apply, $this->employee->uuid);
    }

    private function storeSession(User $user, LegalEntity $entity, string $token, int $ageOffset = 0, bool $expired = false): void
    {
        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'last_activity' => now()->timestamp + $ageOffset,
            'payload' => base64_encode(serialize(['auth_token' => $token, 'ehealth_legal_entity_uuid' => $entity->uuid,
                'auth_token_expires_at' => ($expired ? now()->subHour() : now()->addHour())->timestamp,
                'auth_token_scopes' => ['employee:details']])),
        ]);
    }

    private function fakeDetails(array|callable ...$payloads): void
    {
        $responses = [];
        foreach ($payloads as $payload) {
            $response = Mockery::mock(EHealthResponse::class);
            $response->shouldReceive('getData')->once()->andReturnUsing(fn () => is_callable($payload) ? $payload() : $payload);
            $responses[] = $response;
        }
        $this->api->shouldReceive('getDetails')->with($this->employee->uuid)->times(count($responses))->andReturn(...$responses);
    }

    private function remote(array $codes): array
    {
        return ['id' => $this->employee->uuid, 'legal_entity' => ['id' => $this->legalEntity->uuid],
            'party' => ['id' => $this->employee->party->uuid], 'employee_type' => 'SPECIALIST', 'status' => 'APPROVED',
            'doctor' => ['specialities' => array_map(fn ($code) => $this->certificate($code), $codes)]];
    }

    private function certificate(string $code): array
    {
        return ['speciality' => $code, 'speciality_officio' => $code === 'THERAPIST', 'level' => 'SECOND',
            'qualification_type' => 'AWARDING', 'attestation_name' => 'MOH', 'attestation_date' => '2024-01-01', 'certificate_number' => 'CERT-'.$code];
    }
}
