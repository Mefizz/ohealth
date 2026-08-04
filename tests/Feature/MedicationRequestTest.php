<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Classes\eHealth\Api\MedicationRequest;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\MedicalEvents\MedicationRequestLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class MedicationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->legalEntity = LegalEntity::factory()->create();
        $this->employee = Employee::factory()->create(['user_id' => $this->user->id, 'legal_entity_id' => $this->legalEntity->id]);
    }

    public function test_it_can_prequalify_medication_request()
    {
        $mockResponse = [
            'data' => [
                ['status' => 'valid']
            ]
        ];

        $mockApi = Mockery::mock('alias:' . MedicationRequest::class);
        $mockApi->shouldReceive('preQualify')
            ->once()
            ->with(['person_id' => 'patient-123'])
            ->andReturn($mockResponse);

        $service = new MedicationRequestLifecycleService();
        $result = $service->preQualify(['person_id' => 'patient-123']);

        $this->assertEquals('valid', $result[0]['status']);
    }

    public function test_medication_request_index_component_renders()
    {
        $this->actingAs($this->user);

        Livewire::test('medication-request.medication-request-index', ['legalEntity' => $this->legalEntity])
            ->assertStatus(200)
            ->assertSee('Електронні Рецепти');
    }

    public function test_medication_request_form_component_prequalify()
    {
        $this->actingAs($this->user);

        // Mock the Lifecycle Service
        $mockService = Mockery::mock(MedicationRequestLifecycleService::class);
        $mockService->shouldReceive('preQualify')->once()->andReturn([]);
        $this->app->instance(MedicationRequestLifecycleService::class, $mockService);

        Livewire::test('medication-request.medication-request-form', ['legalEntity' => $this->legalEntity])
            ->set('patientId', 'uuid-123')
            ->set('medicalProgram', 'program-123')
            ->set('dosageInstruction', 'Take 1 pill')
            ->set('duration', 30)
            ->call('preQualify')
            ->assertSee('PreQualify успішно пройдено');
    }
}
