<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CarePlan;
use App\Models\CarePlanActivity;
use App\Repositories\CarePlanActivityRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CarePlanActivityRepositoryTest extends TestCase
{
    public function test_device_activity_payload_uses_integer_quantity(): void
    {
        $carePlan = new CarePlan(['uuid' => (string) Str::uuid()]);

        $deviceUuid = (string) Str::uuid();

        $activity = new CarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'kind' => 'device_request',
            'product_reference' => $deviceUuid,
            'product_codeable_concept' => '30221',
            'program' => (string) Str::uuid(),
            'quantity' => 1,
            'quantity_system' => 'device_unit',
            'quantity_code' => 'piece',
            'scheduled_period_start' => Carbon::parse('2026-06-23')->format('Y-m-d'),
            'scheduled_period_end' => Carbon::parse('2026-06-30')->format('Y-m-d'),
        ]);
        $activity->setRelation('carePlan', $carePlan);

        $payload = (new CarePlanActivityRepository())->formatCarePlanActivityRequest($activity);

        $this->assertIsInt($payload['detail']['quantity']['value']);
        $this->assertSame(1, $payload['detail']['quantity']['value']);
        $this->assertArrayHasKey('product_codeable_concept', $payload['detail']);
        $this->assertSame('30221', $payload['detail']['product_codeable_concept']['coding'][0]['code']);
        $this->assertArrayNotHasKey('product_reference', $payload['detail']);
    }

    public function test_device_activity_uses_device_definition_when_program_allows_both(): void
    {
        $carePlan = new CarePlan(['uuid' => (string) Str::uuid()]);

        $deviceUuid = '43a426fa-a74b-4fed-9075-37ef0de781d6';

        $activity = new CarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'kind' => 'device_request',
            'product_reference' => $deviceUuid,
            'product_codeable_concept' => '30221',
            'program' => '3e56c84a-808c-46a9-94d1-df4a439a50d2',
            'quantity' => 100,
            'quantity_system' => 'device_unit',
            'quantity_code' => 'piece',
            'scheduled_period_start' => Carbon::parse('2026-06-23')->format('Y-m-d'),
            'scheduled_period_end' => Carbon::parse('2026-06-30')->format('Y-m-d'),
        ]);
        $activity->setRelation('carePlan', $carePlan);

        $payload = (new CarePlanActivityRepository())->formatCarePlanActivityRequest($activity);

        $this->assertArrayHasKey('product_reference', $payload['detail']);
        $this->assertSame($deviceUuid, $payload['detail']['product_reference']['identifier']['value']);
        $this->assertArrayNotHasKey('product_codeable_concept', $payload['detail']);
    }

    public function test_device_activity_uses_classification_for_glucose_program(): void
    {
        $carePlan = new CarePlan(['uuid' => (string) Str::uuid()]);

        $deviceUuid = (string) Str::uuid();

        $activity = new CarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'kind' => 'device_request',
            'product_reference' => $deviceUuid,
            'product_codeable_concept' => 'W0101060101',
            'program' => '0cefbce3-6dd2-45bd-b1e6-983fc055d5e0',
            'quantity' => 50,
            'quantity_system' => 'device_unit',
            'quantity_code' => 'piece',
            'scheduled_period_start' => Carbon::parse('2026-06-23')->format('Y-m-d'),
            'scheduled_period_end' => Carbon::parse('2026-06-30')->format('Y-m-d'),
        ]);
        $activity->setRelation('carePlan', $carePlan);

        $payload = (new CarePlanActivityRepository())->formatCarePlanActivityRequest($activity);

        $this->assertArrayHasKey('product_codeable_concept', $payload['detail']);
        $this->assertSame('W0101060101', $payload['detail']['product_codeable_concept']['coding'][0]['code']);
        $this->assertArrayNotHasKey('product_reference', $payload['detail']);
    }

    public function test_build_device_prequalify_payload_uses_signing_product(): void
    {
        $carePlan = new CarePlan(['uuid' => (string) Str::uuid()]);

        $activity = new CarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'kind' => 'device_request',
            'product_reference' => '43a426fa-a74b-4fed-9075-37ef0de781d6',
            'product_codeable_concept' => '30221',
            'program' => '3e56c84a-808c-46a9-94d1-df4a439a50d2',
            'quantity' => 100,
            'quantity_system' => 'device_unit',
            'quantity_code' => 'piece',
            'reason_reference' => ['Condition/' . Str::uuid()],
        ]);
        $activity->setRelation('carePlan', $carePlan);

        $payload = (new CarePlanActivityRepository())->buildDevicePrequalifyPayload($activity, $carePlan, [
            'person_uuid' => (string) Str::uuid(),
            'encounter_uuid' => null,
            'employee_uuid' => (string) Str::uuid(),
            'legal_entity_uuid' => (string) Str::uuid(),
        ]);

        $this->assertArrayHasKey('device_request', $payload);
        $this->assertSame(
            '43a426fa-a74b-4fed-9075-37ef0de781d6',
            $payload['device_request']['code']['coding'][0]['code']
        );
    }

    public function test_service_activity_payload_uses_decimal_quantity(): void
    {
        $carePlan = new CarePlan(['uuid' => (string) Str::uuid()]);

        $activity = new CarePlanActivity([
            'uuid' => (string) Str::uuid(),
            'status' => 'draft',
            'kind' => 'service_request',
            'product_reference' => '97019-00',
            'quantity' => 1,
            'scheduled_period_start' => Carbon::parse('2026-06-23')->format('Y-m-d'),
            'scheduled_period_end' => Carbon::parse('2026-06-30')->format('Y-m-d'),
        ]);
        $activity->setRelation('carePlan', $carePlan);

        $payload = (new CarePlanActivityRepository())->formatCarePlanActivityRequest($activity);

        $this->assertIsFloat($payload['detail']['quantity']['value']);
    }
}
