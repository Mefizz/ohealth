<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Classes\eHealth\Api\ServiceRequestApi;
use App\Models\Employee\Employee;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\MedicalEvents\ReferralRequestLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->legalEntity = LegalEntity::factory()->create();
        $this->employee = Employee::factory()->create(['user_id' => $this->user->id, 'legal_entity_id' => $this->legalEntity->id]);
    }

    public function test_it_can_find_referral_by_requisition()
    {
        $mockResponse = [
            'data' => [
                ['id' => 'referral-uuid-123', 'status' => 'active']
            ]
        ];

        $mockApi = Mockery::mock('alias:' . ServiceRequestApi::class);
        $mockApi->shouldReceive('getServiceRequest')
            ->once()
            ->with(['requisition' => '1234-5678-9012-3456'])
            ->andReturn($mockResponse);

        $service = new ReferralRequestLifecycleService();
        $result = $service->process('1234-5678-9012-3456');

        $this->assertEquals('referral-uuid-123', $result[0]['id']);
    }

    public function test_it_can_complete_referral()
    {
        $mockResponse = [
            'data' => [
                'id' => 'referral-uuid-123',
                'status' => 'completed'
            ]
        ];

        $mockApi = Mockery::mock('alias:' . ServiceRequestApi::class);
        $mockApi->shouldReceive('completeServiceRequest')
            ->once()
            ->with('referral-uuid-123', [
                'status' => 'completed',
                'based_on' => [
                    ['identifier' => ['value' => 'encounter-uuid']]
                ]
            ])
            ->andReturn($mockResponse);

        $service = new ReferralRequestLifecycleService();
        $result = $service->complete('referral-uuid-123', 'encounter-uuid');

        $this->assertEquals('completed', $result['status']);
    }

    public function test_referral_index_component_renders()
    {
        $this->actingAs($this->user);

        Livewire::test('referral.referral-index', ['legalEntity' => $this->legalEntity])
            ->assertStatus(200)
            ->assertSee('Пошук направлень');
    }
}
