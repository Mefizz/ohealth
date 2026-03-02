<?php

use App\Livewire\TreatmentPlan\Approvals\TreatmentPlanApprovalManager;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(\Tests\TestCase::class);

it('renders the approval manager component successfully', function () {
    Http::fake([
        '*/api/approvals*' => Http::response(['data' => []], 200),
    ]);

    Livewire::test(TreatmentPlanApprovalManager::class, [
        'patientId' => 'ffd7eeef-42aa-4c07-b648-2831b14ea4fb',
        'carePlanId' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e'
    ])
    ->assertStatus(200)
    ->assertSee('Доступи до Плану лікування')
    ->assertSee('Запитати доступ (Write)');
});

it('requests access and shows sms modal', function () {
    // Fake the GET request to return 0 approvals
    // Fake the POST request to return a new approval that requires SMS
    Http::fake([
        '*/api/approvals*' => Http::sequence()
            ->push(['data' => []], 200) // First call to loadApprovals on mount
            ->push(['data' => ['id' => 'new-approval-id', 'status' => 'new']], 200) // Second call to requestAccess (POST)
    ]);

    Livewire::test(TreatmentPlanApprovalManager::class, [
        'patientId' => 'ffd7eeef-42aa-4c07-b648-2831b14ea4fb',
        'carePlanId' => 'a01da6df-b152-45e0-bfea-e7ccdf12586e'
    ])
    ->call('requestAccess')
    ->assertSet('showSmsModal', true)
    ->assertSet('pendingApprovalId', 'new-approval-id');
});
