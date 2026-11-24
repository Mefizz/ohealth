<?php

declare(strict_types=1);

namespace Tests\Unit\CarePlan;

use App\Livewire\CarePlan\CarePlanComponent;
use App\Livewire\CarePlan\CarePlanShow;
use App\Livewire\CarePlan\Forms\CarePlanForm;
use App\Models\CarePlan;
use App\Repositories\CarePlanRepository;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class CarePlanFhirWorkflowTest extends TestCase
{
    public function test_allocated_draft_uuid_still_dispatches_the_plan_creation_action(): void
    {
        $component = new class extends CarePlanComponent
        {
            public bool $signedPlan = false;

            protected function authorizeCarePlanWrite(): void
            {
            }
            protected function rulesForSigning(): array
            {
                return ['actionType' => 'in:sign_plan'];
            }
            protected function signPlan(): void
            {
                $this->signedPlan = true;
            }
            protected function renderCarePlan()
            {
                return null;
            }
        };
        $component->carePlan = new CarePlan(['uuid' => '8d08ce22-82dd-4ac4-bdb9-432fd5aad5dd', 'status' => 'draft']);
        $component->actionType = 'sign_plan';
        $repository = Mockery::mock(CarePlanRepository::class);
        $repository->shouldNotReceive('updateById');

        $this->instance(CarePlanRepository::class, $repository);
        $component->sign();

        $this->assertTrue($component->signedPlan);
        $this->assertSame('draft', $component->carePlan->status);
    }

    public function test_signing_a_draft_preserves_zero_codes_and_reindexes_optional_references(): void
    {
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/plan.json')), true, flags: JSON_THROW_ON_ERROR);
        $reference = $resource['supportingInfo'][0];
        unset($resource['description'], $resource['informWith']);
        $resource['supportingInfo'] = [[], $reference];
        $resource['category']['coding'][0]['code'] = '0';
        $resource['note'] = '0';
        $resource['intent'] = 'plan';
        $resource['status'] = 'draft';

        $payload = CarePlanForm::buildCreateContent($resource);

        $this->assertSame('order', $payload['intent']);
        $this->assertSame('new', $payload['status']);
        $this->assertSame('0', $payload['note']);
        $this->assertSame('0', $payload['category']['coding'][0]['code']);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('inform_with', $payload);
        $this->assertSame([0], array_keys($payload['supporting_info']));
        $this->assertSame($reference['identifier']['value'], $payload['supporting_info'][0]['identifier']['value']);
    }

    public function test_a_draft_without_an_author_fails_validation_before_mapping(): void
    {
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/CarePlan/plan.json')), true, flags: JSON_THROW_ON_ERROR);
        unset($resource['author']);

        $this->expectException(ValidationException::class);
        CarePlanForm::buildCreateContent($resource);
    }

    public function test_completion_does_not_require_signing_credentials_but_cancellation_does(): void
    {
        $component = new CarePlanShow();
        $rules = new ReflectionMethod($component, 'rulesForSigning');
        foreach (['complete', 'complete_activity'] as $action) {
            $component->actionType = $action;
            $actual = $rules->invoke($component);
            $this->assertSame('required|string', $actual['statusReason']);
            $this->assertArrayNotHasKey('form.keyContainerUpload', $actual);
            $this->assertArrayNotHasKey('form.password', $actual);
        }
        $component->actionType = 'cancel';
        $this->assertArrayHasKey('form.keyContainerUpload', $rules->invoke($component));
    }
}
