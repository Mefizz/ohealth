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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
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
    public function test_command_only_queues_local_suspects_and_defaults_to_audit(bool $apply): void
    {
        Bus::fake();
        putenv('EHEALTH_ACCESS_TOKEN=test-operator-token');
        $verifiedPath = tempnam(sys_get_temp_dir(), 'specialities-clear-');
        file_put_contents($verifiedPath, json_encode([$this->employee->uuid], JSON_THROW_ON_ERROR));
        try {
            $imported = $this->employee->replicate();
            $imported->uuid = (string) Str::uuid();
            $imported->position = 'P3';
            $imported->save();
            $imported->specialities()->create($this->certificate('NURSING'));
            $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id] + ($apply ? ['--apply' => true, '--verified-employees' => $verifiedPath] : []))
                ->expectsOutputToContain($apply ? 'Mode: APPLY' : 'Mode: AUDIT')->assertSuccessful();
            Bus::assertBatched(fn ($batch) => count($batch->jobs) === 1
                && $batch->jobs[0]->requestId === $this->request->id && $batch->jobs[0]->apply === $apply
                && $batch->options['queue'] === 'sync');
            Http::assertNothingSent();
            $this->assertSame(2, $this->employee->specialities()->count());
        } finally {
            putenv('EHEALTH_ACCESS_TOKEN');
            unlink($verifiedPath);
        }
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
        $this->fakeDetails($this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
        $this->assertSame(['NURSING'], $other->specialities()->pluck('speciality')->all());
        $this->assertSame($revision, $this->request->revision->fresh()->getAttributes());
        $this->job()->handle($this->repository);
    }

    public function test_soft_deleted_applied_revisions_still_prove_local_creation(): void
    {
        $this->request->revision->delete();
        $this->fakeDetails($this->remote(['THERAPIST']));
        $this->job()->handle($this->repository);
        $this->assertSame(['THERAPIST'], $this->employee->specialities()->pluck('speciality')->all());
    }

    public function test_queue_serialization_preserves_approval_without_exposing_the_token(): void
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
        putenv('EHEALTH_ACCESS_TOKEN=test-operator-token');
        try {
            $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id])
                ->assertExitCode(2);
            Bus::assertNothingBatched();
        } finally {
            putenv('EHEALTH_ACCESS_TOKEN');
        }
    }

    public function test_audit_and_centrally_registered_additional_codes_are_preserved(): void
    {
        $this->fakeDetails($this->remote(['THERAPIST']), $this->remote(['THERAPIST', 'NURSING']));
        $this->job(false)->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
        $this->job()->handle($this->repository);
        $this->assertSame(2, $this->employee->specialities()->count());
    }

    public function test_apply_without_a_reviewed_uuid_list_does_not_queue_jobs(): void
    {
        Bus::fake();
        $this->artisan('specialities_clear', ['--legal-entity' => $this->legalEntity->id, '--user' => $this->operator->id, '--apply' => true])
            ->assertExitCode(2);
        Bus::assertNothingBatched();
    }

    public function test_old_relink_to_a_unique_imported_employee_is_not_origin_proof(): void
    {
        $imported = $this->employee->replicate();
        $imported->uuid = (string) Str::uuid();
        $imported->save();
        foreach (['THERAPIST', 'NURSING'] as $code) {
            $imported->specialities()->create($this->certificate($code));
        }
        $this->request->update(['employee_id' => $imported->id]);
        $this->employee->delete();
        $plan = $this->repository->refreshSpecialityClearPlan($this->request->id, $this->legalEntity->id);
        $this->assertNotNull($plan, 'A historical wrong link can pass attribute matching; it still needs explicit origin approval.');
        $job = new ClearEmployeeSpecialities($this->request->id, $this->legalEntity->id, $this->operator, Crypt::encryptString('test-operator-token'), true);
        try {
            $job->handle($this->repository);
            $this->fail('An old employee_id link must not authorize imported-employee repair.');
        } catch (\LogicException $exception) {
            $this->assertSame('Applying repair requires an explicitly reviewed Employee UUID.', $exception->getMessage());
            $this->assertSame(2, $imported->specialities()->count());
            Http::assertNothingSent();
        }
    }

    public static function unprovenSources(): array
    {
        return ['imported' => ['imported'], 'wrong submit ID' => ['response'], 'edit request' => ['edit'],
            'wrong party' => ['party'], 'pending edit' => ['pending'], 'ambiguous positions' => ['ambiguous']];
    }

    #[DataProvider('unprovenSources')]
    public function test_unproven_and_busy_records_do_not_send_requests(string $case): void
    {
        $revision = $this->request->revision;
        if ($case === 'pending') {
            EmployeeRequest::create(['employee_id' => $this->employee->id, 'legal_entity_id' => $this->legalEntity->id, 'status' => RequestStatus::NEW]);
        } elseif ($case === 'ambiguous') {
            $other = $this->employee->replicate();
            $other->uuid = (string) Str::uuid();
            $other->save();
        } elseif ($case === 'imported' || $case === 'response') {
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
        $this->job()->handle($this->repository);
        Http::assertNothingSent();
        $this->assertSame(2, $this->employee->specialities()->count());
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
        return new ClearEmployeeSpecialities($this->request->id, $this->legalEntity->id, $this->operator, Crypt::encryptString('test-operator-token'), $apply, $apply ? $this->employee->uuid : null);
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
