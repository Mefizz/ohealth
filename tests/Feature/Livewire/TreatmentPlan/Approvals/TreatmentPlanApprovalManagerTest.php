<?php

use App\Classes\eHealth\Api\Approval;
use App\Classes\eHealth\EHealthResponse;
use App\Livewire\TreatmentPlan\Approvals\TreatmentPlanApprovalManager;
use Livewire\Livewire;

uses(\Tests\TestCase::class);

it('renders the approval manager component successfully', function () {
    $mockApproval = \Mockery::mock(Approval::class);
    $mockApproval->shouldReceive('getApprovals')->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['data' => []]))
    )));
    app()->instance(Approval::class, $mockApproval);

    Livewire::test(TreatmentPlanApprovalManager::class, [
        'patientId' => 'ffd7eeef-42aa-4c07-b648-2831b14ea4fb',
        'carePlanId' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e'
    ])
    ->assertStatus(200)
    ->assertSee('Доступи до Плану лікування')
    ->assertSee('Запитати доступ (Write)');
});

it('requests access and shows sms modal', function () {
    $mockApproval = \Mockery::mock(Approval::class);
    $mockApproval->shouldReceive('getApprovals')->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['data' => []]))
    )));
    
    $mockApproval->shouldReceive('requestAccess')->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'data' => [
                'id' => 'new-approval-id',
                'status' => 'new'
            ]
        ]))
    )));
    
    app()->instance(Approval::class, $mockApproval);

    $this->withoutExceptionHandling();

    Livewire::test(TreatmentPlanApprovalManager::class, [
        'patientId' => 'ffd7eeef-42aa-4c07-b648-2831b14ea4fb',
        'carePlanId' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e'
    ])
    ->call('requestAccess')
    ->assertSet('showSmsModal', true)
    ->assertSet('pendingApprovalId', 'new-approval-id');
});

it('cancels an approval', function () {
    $mockApproval = \Mockery::mock(Approval::class);
    
    // First load approvals returns 1 active approval
    $mockApproval->shouldReceive('getApprovals')->times(1)->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['data' => [
            [
                'id' => 'approval-to-cancel',
                'status' => 'active',
                'access_level' => 'write',
                'granted_resources' => [
                    ['identifier' => ['value' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e']]
                ]
            ]
        ]]))
    )));

    // Cancel call
    $mockApproval->shouldReceive('cancel')->once()->with('approval-to-cancel', [])->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['data' => ['status' => 'entered_in_error']]))
    )));

    // Second load approvals after cancel returns empty
    $mockApproval->shouldReceive('getApprovals')->times(1)->andReturn(new EHealthResponse(new \Illuminate\Http\Client\Response(
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['data' => []]))
    )));

    app()->instance(Approval::class, $mockApproval);

    Livewire::test(TreatmentPlanApprovalManager::class, [
        'patientId' => 'ffd7eeef-42aa-4c07-b648-2831b14ea4fb',
        'carePlanId' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e'
    ])
    ->assertSee('Скасувати')
    ->call('cancelApproval', 'approval-to-cancel')
    ->assertDontSee('Скасувати');
});
