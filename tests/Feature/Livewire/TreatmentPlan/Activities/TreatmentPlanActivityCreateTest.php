<?php

namespace Tests\Feature\Livewire\TreatmentPlan\Activities;

use App\Livewire\TreatmentPlan\Activities\TreatmentPlanActivityCreate;
use App\Models\TreatmentPlan\TreatmentPlan;
use App\Models\TreatmentPlan\TreatmentPlanActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(\Tests\TestCase::class, RefreshDatabase::class);

it('renders the activity create buttons successfully empty state', function () {
    Livewire::test(TreatmentPlanActivityCreate::class, ['treatmentPlanId' => 1])
        ->assertStatus(200)
        ->assertSee('Нове призначення на послуги')
        ->assertSee('Нове призначення на ліки');
});

it('opens the medication form and displays fields', function () {
    Livewire::test(TreatmentPlanActivityCreate::class, ['treatmentPlanId' => 1])
        ->call('openModal', 'medication_request')
        ->assertSet('showModal', true)
        ->assertSet('detail_kind', 'medication_request')
        ->assertSee('Нове призначення на ліки')
        ->assertSee('Лікарський засіб (МНН)')
        ->assertSee('Разова доза');
});

it('opens the service form and displays fields', function () {
    Livewire::test(TreatmentPlanActivityCreate::class, ['treatmentPlanId' => 1])
        ->call('openModal', 'service_request')
        ->assertSet('showModal', true)
        ->assertSet('detail_kind', 'service_request')
        ->assertSee('Нове призначення на послуги')
        ->assertSee('Код послуги')
        ->assertDontSee('Разова доза');
});

it('saves a valid medication request activity', function () {
    // Create a dummy treatment plan since we don't have a factory
    $plan = TreatmentPlan::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'category' => 'test',
        'intention' => 'test',
        'terms_service' => 'test',
        'name_treatment_plan' => 'Test Plan',
        'status' => 'NEW'
    ]);

    Livewire::test(TreatmentPlanActivityCreate::class, ['treatmentPlanId' => $plan->id])
        ->call('openModal', 'medication_request')
        ->set('code', 'Aspirin')
        ->set('quantity_value', 2)
        ->set('quantity_code', 'упаковки')
        ->set('dose_value', '1 tab')
        ->set('frequency', 'BID')
        ->call('saveActivity')
        ->assertHasNoErrors()
        ->assertDispatched('activity-added');

    $this->assertDatabaseHas('treatment_plan_activities', [
        'treatment_plan_id' => $plan->id,
        'detail_kind' => 'medication_request',
        'code' => 'Aspirin',
        'quantity_value' => 2,
    ]);

    // Check JSON timing
    $activity = TreatmentPlanActivity::first();
    expect($activity->timing['frequency'])->toBe('BID');
    expect($activity->timing['dose'])->toBe('1 tab');
});
