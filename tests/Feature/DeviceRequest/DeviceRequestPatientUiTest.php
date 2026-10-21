<?php

declare(strict_types=1);

namespace Tests\Feature\DeviceRequest;

use App\Classes\eHealth\Api\Patient\DeviceRequest as DeviceRequestApi;
use App\Classes\eHealth\EHealthResponse;
use App\Exceptions\EHealth\EHealthConnectionException;
use App\Livewire\DeviceRequest\DeviceRequestIndex;
use App\Models\LegalEntity;
use App\Models\Person\Person;
use App\Models\User;
use App\Services\SignatureService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DeviceRequestPatientUiTest extends TestCase
{
    use DatabaseTransactions;

    private LegalEntity $legalEntity;
    private Person $person;
    private DeviceRequestApi $api;
    private string $requestUuid;
    private bool $canReadDispenses = true;
    private bool $canReadRequests = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legalEntity = LegalEntity::create(['uuid' => (string) Str::uuid(), 'status' => 'ACTIVE']);
        $this->instance('legalEntity', $this->legalEntity);
        $this->person = Person::create(['uuid' => (string) Str::uuid(), 'gender' => 'MALE']);
        $this->person->names()->create(['first_name' => 'Тест', 'last_name' => 'Пацієнт', 'language' => 'uk']);
        $this->actingAs(User::create(['email' => Str::random(12).'@example.com', 'password' => 'unused']));
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if ($ability === 'create') {
                return false;
            }
            if ($ability === 'device_dispense:read' || ($ability === 'perform' && end($arguments) === 'device_dispense:read')) {
                return $this->canReadDispenses;
            }
            if ($ability === 'viewAny' && ($arguments[0] ?? null) === \App\Models\MedicalEvents\Sql\DeviceRequestRequest::class) {
                return $this->canReadRequests;
            }

            return true;
        });
        $signer = Mockery::mock(SignatureService::class);
        $signer->shouldReceive('getCertificateAuthorities')->andReturn([]);
        $this->instance(SignatureService::class, $signer);
        $this->api = Mockery::mock(DeviceRequestApi::class);
        $this->api->shouldReceive('getBySearchParams')->andReturn($this->response([]));
        $this->instance(DeviceRequestApi::class, $this->api);
        $this->requestUuid = (string) Str::uuid();
        $this->api->shouldReceive('getById')->with($this->person->uuid, $this->requestUuid)
            ->andReturn($this->response(['id' => $this->requestUuid, 'status' => 'active', 'requisition' => 'TEST-REQUEST']));
    }

    public function testEmptyDispensesAreVisibleOnlyAfterSuccessfulLoading(): void
    {
        $this->api->shouldReceive('getDispenses')->once()
            ->with($this->person->uuid, ['based_on' => $this->requestUuid, 'page' => 1])
            ->andReturn($this->response([]));

        $this->patientComponent()->call('showDetails', $this->requestUuid)
            ->assertDontSee(__('device-requests.labels.dispenses_empty'))
            ->call('loadDispenses')->assertSet('dispensesLoaded', true)
            ->assertSee(__('device-requests.labels.dispenses_empty'))
            ->assertSee(__('device-requests.labels.dispenses_hint'));
    }

    public function testFailedReloadClearsPreviousDispensesAndShowsConnectionErrorOnce(): void
    {
        $this->api->shouldReceive('getDispenses')->once()->andReturn($this->response([['id' => 'old-dispense']]));
        $this->api->shouldReceive('getDispenses')->once()->andThrow(new EHealthConnectionException('Timeout'));

        $component = $this->patientComponent()->call('showDetails', $this->requestUuid)
            ->call('loadDispenses')->assertSet('dispensesLoaded', true)
            ->call('loadDispenses')->assertSet('dispensesLoaded', false)
            ->assertSet('dispenses', [])->assertSet('dispenseDetails', [])
            ->assertDontSee(__('device-requests.labels.dispenses_empty'))
            ->assertSee(__('messages.connection_exception'));

        $component->call('search')->assertDontSee(__('messages.connection_exception'));
    }

    public function testChangingRequestClearsDispensesAndTheirLoadedState(): void
    {
        $this->api->shouldReceive('getDispenses')->andReturn($this->response([['id' => 'old-dispense']]));
        $otherUuid = (string) Str::uuid();
        $this->api->shouldReceive('getById')->with($this->person->uuid, $otherUuid)
            ->andReturn($this->response(['id' => $otherUuid, 'status' => 'active']));

        $this->patientComponent()->call('showDetails', $this->requestUuid)->call('loadDispenses')
            ->call('showDetails', $otherUuid)->assertSet('dispenses', [])
            ->assertSet('dispensesLoaded', false)->assertSet('dispenseDetails', []);
    }

    public function testAllDispensePagesAreLoadedForTheSelectedRequest(): void
    {
        foreach ([1, 2] as $page) {
            $this->api->shouldReceive('getDispenses')->once()
                ->with($this->person->uuid, ['based_on' => $this->requestUuid, 'page' => $page])
                ->andReturn($this->response([['id' => 'dispense-'.$page]], $page, 2));
        }

        $this->patientComponent()->call('showDetails', $this->requestUuid)->call('loadDispenses')
            ->assertSet('dispenses', [['id' => 'dispense-1'], ['id' => 'dispense-2']]);
    }

    public function testForeignDispenseDoesNotLeavePreviousDetailsOnScreen(): void
    {
        $ownUuid = (string) Str::uuid();
        $foreignUuid = (string) Str::uuid();
        $this->api->shouldReceive('getDispense')->with($this->person->uuid, $ownUuid)->once()
            ->andReturn($this->response(['id' => $ownUuid, 'based_on' => ['identifier' => ['value' => $this->requestUuid]]]));
        $this->api->shouldReceive('getDispense')->with($this->person->uuid, $foreignUuid)->once()
            ->andReturn($this->response(['id' => $foreignUuid, 'based_on' => ['identifier' => ['value' => (string) Str::uuid()]]]));

        $this->patientComponent()->call('showDetails', $this->requestUuid)->call('showDispense', $ownUuid)
            ->assertSet('dispenseDetails.id', $ownUuid)->call('showDispense', $foreignUuid)
            ->assertSet('dispenseDetails', []);
    }

    public function testDispenseLoadingRequiresPermissionAndASelectedRequest(): void
    {
        $this->api->shouldNotReceive('getDispenses');
        $this->patientComponent()->call('loadDispenses')->assertHasErrors(['selectedId']);
        $this->canReadDispenses = false;
        $this->patientComponent()->call('showDetails', $this->requestUuid)->call('loadDispenses')->assertForbidden();
    }

    public function testPatientNavigationContainsAuthorizedRequestTabAndNotTheStub(): void
    {
        $this->patientComponent()->assertSee(__('device-requests.labels.patient_tab'))
            ->assertSee(route('device-requests.index', [$this->legalEntity, 'person' => $this->person->id]), false)
            ->assertDontSee(__('patients.prescription_requests'));

        $this->canReadRequests = false;
        $this->patientComponent()->assertDontSee(__('device-requests.labels.patient_tab'));
    }

    private function patientComponent(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(DeviceRequestIndex::class, ['legalEntity' => $this->legalEntity, 'person' => $this->person]);
    }

    private function response(array $data, int $page = 1, int $totalPages = 1): EHealthResponse
    {
        return new EHealthResponse(new Response(200, [], json_encode([
            'data' => $data, 'paging' => ['page_number' => $page, 'total_pages' => $totalPages],
        ], JSON_THROW_ON_ERROR)));
    }
}
