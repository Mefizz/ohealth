<?php

declare(strict_types=1);

namespace Tests\Feature\Referral;

use App\Classes\eHealth\Api\Patient\ServiceRequest;
use App\Classes\eHealth\Api\Responses\Collections\ServiceRequestDetails;
use App\Classes\eHealth\EHealthResponse;
use App\Livewire\Person\Forms\PatientApprovalForm;
use App\Livewire\Person\Records\PatientApproval;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\Approval;
use App\Models\Person\Person;
use App\Models\User;
use App\Services\MedicalEvents\PatientApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ServiceRequestApprovalTest extends TestCase
{
    use DatabaseTransactions;

    protected Person $person;
    protected PatientApproval $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->person = Person::create(['uuid' => (string) Str::uuid(), 'gender' => 'MALE']);
        $legalEntity = (new LegalEntity())->forceFill(['id' => 1, 'uuid' => (string) Str::uuid()]);
        $this->instance('legalEntity', $legalEntity);
        $employee = (new Employee())->forceFill([
            'uuid' => (string) Str::uuid(), 'legal_entity_id' => $legalEntity->id,
            'employee_type' => 'DOCTOR', 'status' => 'APPROVED', 'is_active' => true,
        ]);
        $user = Mockery::mock(User::class);
        $user->shouldReceive('cannot')->with('create', Approval::class)->andReturnFalse();
        $user->shouldReceive('activeDoctorEmployee')->andReturn($employee);
        Auth::shouldReceive('user')->andReturn($user);

        $this->component = new PatientApproval();
        $this->component->form = new PatientApprovalForm($this->component, 'form');
        $this->component->personId = $this->person->id;
        $this->component->mode = 'service_request';
        $this->component->resourceIds = [(string) Str::uuid()];
        $this->component->selectedAuthUuid = (string) Str::uuid();
        $this->component->grantToLegalEntity = true;
        $this->mock(PatientApprovalService::class)->shouldReceive('availableAuthenticationMethods')->andReturn([
            ['uuid' => $this->component->selectedAuthUuid, 'type' => 'OTP'],
        ]);
    }

    public function test_approval_preview_and_submission_use_the_validated_permitted_resources(): void
    {
        $uuid = $this->component->resourceIds[0];
        $episodeUuid = (string) Str::uuid();
        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('validate')->twice()->andReturn(new ServiceRequestDetails([
            'id' => $uuid,
            'requisition' => '0000-9999-8888-7777',
            'status' => 'active',
            'permitted_resources' => [[
                'identifier' => [
                    'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'episode_of_care']]],
                    'value' => $episodeUuid,
                ],
            ]],
        ]));
        $this->mock(ServiceRequest::class)->shouldReceive('getDetails')->twice()
            ->with($this->person->uuid, $uuid)->andReturn($response);

        $this->component->remoteReferrals = [$uuid => ['requisition' => '0000-1111-2222-3333']];
        $this->component->showReferralDetails($uuid);
        $this->assertSame('referral', $this->component->previewSection);
        $this->assertSame(__('approval.create.referral_prefix').' 0000-9999-8888-7777', $this->component->previewTitle);
        $this->assertSame([['code' => 'episode_of_care', 'name' => $episodeUuid]], $this->component->previewRows);

        app(PatientApprovalService::class)->shouldReceive('create')->once()->withArgs(function (Person $person, array $payload) use ($uuid): bool {
            return $person->is($this->person)
                && data_get($payload, 'service_request.identifier.value') === $uuid
                && data_get($payload, 'granted_to.identifier.value') === legalEntity()->uuid
                && $payload['authorize_with'] === $this->component->selectedAuthUuid
                && $payload['access_level'] === 'read';
        })->andReturn(['approval_id' => null, 'link_id' => 35]);
        $this->component->create();
        $this->assertSame(PatientApproval::STEP_PROCESSING, $this->component->step);
        $this->assertSame(35, $this->component->linkId);
    }

    public function test_approval_submission_rejects_an_invalid_remote_response(): void
    {
        $uuid = $this->component->resourceIds[0];
        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('validate')->once()->andThrow(ValidationException::withMessages([
            'id' => __('care-plan.referral_full_details_required'),
        ]));
        $this->mock(ServiceRequest::class)->shouldReceive('getDetails')->once()
            ->with($this->person->uuid, $uuid)->andReturn($response);

        app(PatientApprovalService::class)->shouldNotReceive('create');
        try {
            $this->component->create();
            $this->fail('An invalid referral must block approval submission.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('approval.create.referral_check_failed')], $exception->errors()['resourceIds']);
        }
    }
}
