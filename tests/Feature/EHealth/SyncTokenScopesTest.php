<?php

declare(strict_types=1);

namespace Tests\Feature\EHealth;

use App\Auth\EHealth\Services\SyncTokenContext;
use App\Auth\EHealth\Services\TokenStorage;
use App\Classes\eHealth\Api\Party as PartyApi;
use App\Classes\eHealth\Api\Auth as AuthApi;
use App\Classes\eHealth\Api\Job as JobApi;
use App\Classes\eHealth\EHealthResponse;
use App\Enums\JobStatus;
use App\Events\EHealthUserLogin;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Jobs\CompleteSync;
use App\Jobs\EmployeeSync;
use App\Jobs\PartyVerificationSync;
use App\Jobs\RemoteEHealthLinksProcessing;
use App\Listeners\OnRegularLoginSyncronization;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Models\EhealthLink;
use App\Notifications\SyncNotification;
use App\Traits\BatchLegalEntityQueries;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Bus\BatchRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class SyncTokenScopesTest extends TestCase
{
    use DatabaseTransactions;

    private LegalEntity $legalEntity;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database', 'ehealth.api.domain' => 'https://ehealth.invalid']);
        Notification::fake();
        Http::preventStrayRequests();
        Event::listen(JobFailed::class, static function (JobFailed $event): void {
            app('queue.failer')->log($event->connectionName, $event->job->getQueue(), $event->job->getRawBody(), $event->exception);
        });

        $typeId = DB::table('legal_entity_types')->where('name', 'PRIMARY_CARE')->value('id')
            ?? DB::table('legal_entity_types')->insertGetId(['name' => 'PRIMARY_CARE']);
        $this->legalEntity = LegalEntity::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'ACTIVE',
            'sync_status' => JobStatus::COMPLETED->value,
            'legal_entity_type_id' => $typeId,
            'is_active' => true,
        ]);
        $this->user = $this->createUser();
        $this->withSession([config('ehealth.api.oauth.bearer_token') => 'new-token']);
    }

    public function test_login_without_scope_keeps_failed_batch_and_does_not_claim_resume(): void
    {
        $batch = $this->failedBatch([new PartyVerificationSync($this->legalEntity, standalone: true)]);

        $this->login(['employee:read']);

        $this->assertDatabaseHas('job_batches', ['id' => $batch->id, 'failed_jobs' => 1]);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
        Notification::assertNothingSent();
    }

    public function test_login_with_scope_resumes_legacy_batch_using_new_token_and_user(): void
    {
        $job = new PartyVerificationSync($this->legalEntity, standalone: true);
        $job->user = $this->createUser();
        $batch = $this->failedBatch([$job]);

        $this->login([PartyVerificationSync::SCOPE_REQUIRED]);

        $this->assertDatabaseMissing('job_batches', ['id' => $batch->id]);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 1);
        $queued = $this->queuedJob();
        $this->assertSame($this->user->id, $queued->user->id);
        $options = app(BatchRepository::class)->find($queued->batchId)->options;
        $this->assertSame('new-token', Crypt::decryptString($options['token']));
        $this->assertSame([PartyVerificationSync::SCOPE_REQUIRED], $options['tokenScopes']);
        Notification::assertSentTo($this->user, SyncNotification::class, fn ($notification) => $notification->toArray($this->user)['action'] === 'resumed');
    }

    public function test_partial_batch_keeps_jobs_that_need_other_scopes(): void
    {
        $batch = $this->failedBatch([
            new PartyVerificationSync($this->legalEntity, standalone: true),
            new EmployeeSync($this->legalEntity, standalone: true),
        ]);

        $this->login(['employee:read']);

        $this->assertDatabaseHas('job_batches', ['id' => $batch->id, 'failed_jobs' => 1, 'pending_jobs' => 1, 'total_jobs' => 1]);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertInstanceOf(EmployeeSync::class, $this->queuedJob());
        $this->assertStringContainsString('PartyVerificationSync', DB::table('failed_jobs')->value('payload'));
    }

    public function test_dispatch_failure_preserves_original_failed_records(): void
    {
        $batch = $this->failedBatch([new PartyVerificationSync($this->legalEntity)]);
        Bus::shouldReceive('batch')->once()->andThrow(new RuntimeException('Queue unavailable'));

        try {
            $this->login([PartyVerificationSync::SCOPE_REQUIRED]);
            $this->fail('Expected dispatch failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('job_batches', ['id' => $batch->id, 'failed_jobs' => 1]);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_worker_without_scope_pauses_before_calling_api_and_keeps_job(): void
    {
        $api = Mockery::mock(PartyApi::class);
        $api->shouldNotReceive('getMany');
        $this->instance(PartyApi::class, $api);
        $this->queuePartyJob(['employee:read']);

        $this->runNextJob();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(JobStatus::PAUSED->value, $this->legalEntity->fresh()->partyVerificationSyncStatus);
        $this->assertSame(JobStatus::COMPLETED->value, $this->legalEntity->fresh()->syncStatus);
    }

    public function test_missing_allowances_from_api_fails_once_even_with_outdated_scope_metadata(): void
    {
        $exception = new EHealthResponseException(new Response(new GuzzleResponse(403, [], json_encode([
            'error' => ['message' => 'Your scope does not allow to access this resource. Missing allowances: party_verification:read'],
        ]))));
        $api = Mockery::mock(PartyApi::class);
        $api->shouldReceive('withToken')->once()->with('new-token')->andReturnSelf();
        $api->shouldReceive('getMany')->once()->andThrow($exception);
        $this->instance(PartyApi::class, $api);
        $this->queuePartyJob([PartyVerificationSync::SCOPE_REQUIRED]);

        $this->runNextJob();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(JobStatus::PAUSED->value, $this->legalEntity->fresh()->partyVerificationSyncStatus);
    }

    public function test_other_api_errors_keep_normal_queue_retries(): void
    {
        $exception = new EHealthResponseException(new Response(new GuzzleResponse(503, [], json_encode([
            'error' => ['message' => 'Temporarily unavailable'],
        ]))));
        $api = Mockery::mock(PartyApi::class);
        $api->shouldReceive('withToken')->once()->andReturnSelf();
        $api->shouldReceive('getMany')->once()->andThrow($exception);
        $this->instance(PartyApi::class, $api);
        $this->queuePartyJob([PartyVerificationSync::SCOPE_REQUIRED]);

        $this->runNextJob();

        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseHas('jobs', ['attempts' => 1]);
    }

    public function test_legacy_batch_with_unknown_scopes_fails_closed(): void
    {
        $api = Mockery::mock(PartyApi::class);
        $api->shouldNotReceive('getMany');
        $this->instance(PartyApi::class, $api);
        $this->queuePartyJob(null);

        $this->runNextJob();

        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_legacy_batch_resolves_scopes_only_for_its_exact_session_token(): void
    {
        $this->saveSession($this->user, 'other-token', [PartyVerificationSync::SCOPE_REQUIRED]);
        $this->saveSession($this->user, 'new-token', []);
        $this->queuePartyJob(null);

        $this->runNextJob();

        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_legacy_pagination_retains_resolved_scopes_after_session_is_gone(): void
    {
        $this->saveSession($this->user, 'new-token', [PartyVerificationSync::SCOPE_REQUIRED]);
        $this->fakePartyPages(2);
        $this->queuePartyJob(null);

        $this->runNextJob();
        DB::table('sessions')->delete();
        DB::table('jobs')->update(['available_at' => now()->timestamp]);
        $this->runNextJob();

        $this->assertDatabaseCount('failed_jobs', 0);
        $completion = $this->queuedJob();
        $this->assertInstanceOf(CompleteSync::class, $completion);
        $this->assertSame([PartyVerificationSync::SCOPE_REQUIRED], app(BatchRepository::class)->find($completion->batchId)->options['tokenScopes']);
    }

    public function test_nested_chain_pauses_at_unavailable_scope_without_losing_successor(): void
    {
        $batch = $this->failedBatch([new PartyVerificationSync(
            $this->legalEntity,
            new EmployeeSync($this->legalEntity, standalone: true)
        )]);
        $this->fakePartyPages(1);

        $this->login([PartyVerificationSync::SCOPE_REQUIRED]);
        $this->runNextJob();
        $this->runNextJob();

        $this->assertDatabaseMissing('job_batches', ['id' => $batch->id]);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertStringContainsString('EmployeeSync', DB::table('failed_jobs')->value('payload'));
        $this->assertSame(JobStatus::PAUSED->value, $this->legalEntity->fresh()->employeeSyncStatus);
    }

    public function test_retry_uses_token_and_scopes_from_same_matching_session(): void
    {
        $candidate = $this->createUser();
        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Test Employee',
            'employee_type' => 'HR',
            'status' => 'APPROVED',
            'legal_entity_id' => $this->legalEntity->id,
            'is_active' => true,
            'position' => 'Test',
            'start_date' => '2026-01-01',
        ]);
        $candidate->employees()->attach($employee->id);
        $this->saveSession($candidate, 'valid-alternative', [PartyVerificationSync::SCOPE_REQUIRED]);
        $this->saveSession($candidate, 'wrong-entity-token', [PartyVerificationSync::SCOPE_REQUIRED], ['ehealth_legal_entity_uuid' => (string) Str::uuid()]);
        $this->saveSession($candidate, 'expired-token', [PartyVerificationSync::SCOPE_REQUIRED], ['auth_token_expires_at' => now()->subMinute()->timestamp]);
        $this->queuePartyJob([]);

        $this->runNextJob();

        $retry = $this->queuedJob();
        $options = app(BatchRepository::class)->find($retry->batchId)->options;
        $this->assertSame($candidate->id, $options['user']->id);
        $this->assertSame('valid-alternative', Crypt::decryptString($options['token']));
        $this->assertSame([PartyVerificationSync::SCOPE_REQUIRED], $options['tokenScopes']);
    }

    public function test_manual_resume_cannot_borrow_scopes_from_another_session(): void
    {
        $batch = $this->failedBatch([new PartyVerificationSync($this->legalEntity)]);
        $this->saveSession($this->user, 'other-token', [PartyVerificationSync::SCOPE_REQUIRED]);
        $resumer = new class
        {
            use BatchLegalEntityQueries;

            public function resume(stdClass $batch, User $user, LegalEntity $legalEntity): bool
            {
                return $this->restartBatch($batch, $user, Crypt::encryptString('new-token'), $legalEntity);
            }
        };

        $this->assertFalse($resumer->resume($batch, $this->user, $this->legalEntity));
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_session_context_rejects_permissions_without_granted_oauth_scopes(): void
    {
        $this->saveSession($this->user, 'new-token', [], [
            config('ehealth.api.oauth.token_scopes') => [PartyVerificationSync::SCOPE_REQUIRED],
        ]);

        $this->assertNull(app(SyncTokenContext::class)->find($this->user, $this->legalEntity, PartyVerificationSync::SCOPE_REQUIRED));
    }

    public function test_token_storage_keeps_granted_scopes_separate_from_permission_scopes_and_clears_them(): void
    {
        $storage = app(TokenStorage::class);
        $storage->store([
            'value' => 'oauth-token',
            'expires_at' => now()->addHour()->timestamp,
            'details' => ['scope' => 'employee:read party_verification:read', 'refresh_token' => 'refresh'],
        ]);
        $storage->storeScopes(['employee:read']);

        $this->assertSame(['employee:read', 'party_verification:read'], session(SyncTokenContext::GRANTED_SCOPES_KEY));
        $storage->clear();
        $this->assertFalse(session()->has(SyncTokenContext::GRANTED_SCOPES_KEY));
    }

    #[DataProvider('refreshScopes')]
    public function test_refresh_preserves_or_replaces_granted_scopes(?string $returnedScope, array $expected): void
    {
        $this->legalEntity->update(['client_id' => 'test-client', 'client_secret' => 'test-secret']);
        $storage = app(TokenStorage::class);
        $storage->store([
            'value' => 'oauth-token',
            'expires_at' => now()->addHour()->timestamp,
            'details' => ['scope' => 'employee:read party_verification:read', 'refresh_token' => 'refresh'],
        ]);
        session()->put('ehealth_legal_entity_uuid', $this->legalEntity->uuid);
        $data = ['value' => 'refreshed-token', 'expires_at' => now()->addHour()->timestamp, 'details' => ['refresh_token' => 'next-refresh']];

        if ($returnedScope !== null) {
            $data['details']['scope'] = $returnedScope;
        }

        $validator = new class extends AuthApi
        {
            public function validateRefresh(EHealthResponse $response): array
            {
                return $this->validateExtendTokenLifetime($response);
            }
        };
        $response = new EHealthResponse(new GuzzleResponse(200, [], json_encode(['data' => $data])), $validator->validateRefresh(...));
        $api = Mockery::mock(AuthApi::class);
        $api->shouldReceive('extendTokenLifetime')->once()->andReturn($response);
        $this->instance(AuthApi::class, $api);

        $this->assertTrue($storage->refreshBearerToken());
        $this->assertSame('refreshed-token', $storage->getBearerToken());
        $this->assertSame($expected, session(SyncTokenContext::GRANTED_SCOPES_KEY));
    }

    public static function refreshScopes(): array
    {
        return [
            'same grant without repeated scopes' => [null, ['employee:read', 'party_verification:read']],
            'reduced grant' => ['employee:read', ['employee:read']],
        ];
    }

    public function test_async_job_polling_uses_job_read_scope_without_approval_create(): void
    {
        $api = Mockery::mock(JobApi::class);
        $api->shouldReceive('withToken')->once()->with('new-token')->andReturnSelf();
        $api->shouldReceive('getDetails')->once()->with('async-example')->andThrow(new EHealthResponseException(
            new Response(new GuzzleResponse(503, [], json_encode(['error' => ['message' => 'Temporarily unavailable']])))
        ));
        $this->instance(JobApi::class, $api);
        $job = new RemoteEHealthLinksProcessing(
            new EhealthLink(['entity' => 'job', 'href' => '/api/jobs/async-example']),
            $this->legalEntity
        );
        Bus::batch([$job])
            ->withOption('legal_entity_id', $this->legalEntity->id)
            ->withOption('token', Crypt::encryptString('new-token'))
            ->withOption('tokenScopes', ['job:read', 'encounter:write'])
            ->withOption('user', $this->user)
            ->onQueue('sync')
            ->dispatch();

        $this->runNextJob();

        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseHas('jobs', ['attempts' => 1]);
    }

    public function test_two_rejected_tokens_cannot_start_an_endless_replacement_loop(): void
    {
        $candidate = $this->createUser();
        $this->attachEmployee($this->user);
        $this->attachEmployee($candidate);
        $this->saveSession($this->user, 'new-token', [PartyVerificationSync::SCOPE_REQUIRED]);
        $this->saveSession($candidate, 'alternative-token', [PartyVerificationSync::SCOPE_REQUIRED]);
        $api = Mockery::mock(PartyApi::class);
        $api->shouldReceive('withToken')->twice()->andReturnSelf();
        $api->shouldReceive('getMany')->twice()->andThrow(new EHealthResponseException(
            new Response(new GuzzleResponse(403, [], json_encode(['error' => ['message' => 'Missing allowances: party_verification:read']])))
        ));
        $this->instance(PartyApi::class, $api);
        $this->queuePartyJob([PartyVerificationSync::SCOPE_REQUIRED]);

        $this->runNextJob();
        $this->runNextJob();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(JobStatus::PAUSED->value, $this->legalEntity->fresh()->partyVerificationSyncStatus);
    }

    public function test_failure_after_enqueue_rolls_back_new_job_and_restores_old_failed_records(): void
    {
        $batch = $this->failedBatch([new PartyVerificationSync($this->legalEntity)]);
        $resumer = new class
        {
            use BatchLegalEntityQueries {
                restartFailedBatch as private dispatchRestart;
            }

            public function resume(stdClass $batch, User $user, LegalEntity $legalEntity): void
            {
                $this->restartBatch($batch, $user, Crypt::encryptString('new-token'), $legalEntity, [PartyVerificationSync::SCOPE_REQUIRED]);
            }

            protected function restartFailedBatch(User $user, stdClass $batch, string $token, LegalEntity $legalEntity, array $pendingJobs, array $scopes): void
            {
                $this->dispatchRestart($user, $batch, $token, $legalEntity, $pendingJobs, $scopes);

                throw new RuntimeException('Cleanup failed');
            }
        };

        try {
            $resumer->resume($batch, $this->user, $this->legalEntity);
            $this->fail('Expected cleanup failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cleanup failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('job_batches', ['id' => $batch->id, 'failed_jobs' => 1]);
        $this->assertDatabaseCount('job_batches', 1);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    private function attachEmployee(User $user): void
    {
        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Test Employee',
            'employee_type' => 'HR',
            'status' => 'APPROVED',
            'legal_entity_id' => $this->legalEntity->id,
            'is_active' => true,
            'position' => 'Test',
            'start_date' => '2026-01-01',
        ]);
        $user->employees()->attach($employee->id);
    }

    private function createUser(): User
    {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.com',
            'password' => 'unused-test-password',
        ]);
    }

    private function login(array $scopes): void
    {
        (new OnRegularLoginSyncronization())->handle(new EHealthUserLogin(
            $this->user,
            $this->legalEntity,
            $this->user->uuid,
            $scopes
        ));
    }

    private function failedBatch(array $jobs): stdClass
    {
        $batch = app(BatchRepository::class)->store(Bus::batch([])
            ->name('Party Verification Status Sync')
            ->withOption('legal_entity_id', $this->legalEntity->id)
            ->withOption('token', Crypt::encryptString('old-token'))
            ->withOption('user', $this->user));
        $ids = [];

        foreach ($jobs as $job) {
            $ids[] = $uuid = (string) Str::uuid();
            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'sync',
                'payload' => json_encode(['data' => ['command' => serialize($job)]]),
                'exception' => 'Previous synchronization failure',
            ]);
        }

        DB::table('job_batches')->where('id', $batch->id)->update([
            'failed_job_ids' => json_encode($ids),
            'failed_jobs' => count($ids),
            'total_jobs' => count($ids),
            'pending_jobs' => count($ids),
            'cancelled_at' => now()->timestamp,
        ]);

        return DB::table('job_batches')->where('id', $batch->id)->first();
    }

    private function queuePartyJob(?array $scopes): void
    {
        $batch = Bus::batch([new PartyVerificationSync($this->legalEntity, standalone: true)])
            ->name('Party Verification Status Sync')
            ->withOption('legal_entity_id', $this->legalEntity->id)
            ->withOption('token', Crypt::encryptString('new-token'))
            ->withOption('user', $this->user)
            ->onQueue('sync');

        if ($scopes !== null) {
            $batch->withOption('tokenScopes', $scopes);
        }

        $batch->dispatch();
    }

    private function saveSession(User $user, string $token, array $scopes, array $overrides = []): void
    {
        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'last_activity' => now()->timestamp,
            'payload' => base64_encode(serialize(array_merge([
                config('ehealth.api.oauth.bearer_token') => $token,
                SyncTokenContext::GRANTED_SCOPES_KEY => $scopes,
                'ehealth_legal_entity_uuid' => $this->legalEntity->uuid,
                'auth_token_expires_at' => now()->addHour()->timestamp,
            ], $overrides))),
        ]);
    }

    private function queuedJob(): object
    {
        $payload = json_decode(DB::table('jobs')->orderBy('id')->value('payload'), true);

        return unserialize($payload['data']['command']);
    }

    private function runNextJob(): void
    {
        app('queue.worker')->runNextJob('database', 'sync', new WorkerOptions(sleep: 0, maxTries: 3));
    }

    private function fakePartyPages(int $pages): void
    {
        $api = Mockery::mock(PartyApi::class);
        $api->shouldReceive('withToken')->times($pages)->with('new-token')->andReturnSelf();

        for ($page = 1; $page <= $pages; $page++) {
            $response = new EHealthResponse(
                new GuzzleResponse(200, [], json_encode(['data' => [], 'paging' => ['page_number' => $page, 'total_pages' => $pages]])),
                static fn () => [],
                static fn () => []
            );
            $api->shouldReceive('getMany')->once()->with([], $page)->andReturn($response);
        }

        $this->instance(PartyApi::class, $api);
    }
}
