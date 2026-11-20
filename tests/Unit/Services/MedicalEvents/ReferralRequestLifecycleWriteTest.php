<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MedicalEvents;

use App\Classes\eHealth\Api\Patient\ServiceRequest as ServiceRequestApi;
use App\Classes\eHealth\EHealthResponse;
use App\Core\Arr;
use GuzzleHttp\Psr7\Response;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferralRequestLifecycleWriteTest extends TestCase
{
    public function test_pending_receipt_is_validated_without_polling(): void
    {
        $receipt = ['status' => 'pending', 'links' => [['entity' => 'job', 'href' => '/api/jobs/synthetic-job']]];
        $response = new EHealthResponse(new Response(202, [], json_encode(['data' => $receipt], JSON_THROW_ON_ERROR)));

        $this->assertSame($receipt, app(ServiceRequestApi::class)->validateOperation($response));
    }

    public function test_incomplete_synchronous_response_is_rejected(): void
    {
        $response = new EHealthResponse(new Response(201, [], json_encode(['data' => ['status' => 'recalled']], JSON_THROW_ON_ERROR)));
        $this->expectException(ValidationException::class);

        app(ServiceRequestApi::class)->validateOperation($response);
    }

    public function test_pending_receipt_cannot_claim_a_clinical_status(): void
    {
        $response = new EHealthResponse(new Response(202, [], json_encode(['data' => ['status' => 'active']], JSON_THROW_ON_ERROR)));
        $this->expectException(ValidationException::class);

        app(ServiceRequestApi::class)->validateOperation($response);
    }

    public function test_incomplete_embedded_job_resource_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ServiceRequestApi::class)->validateJobResult(['status' => 'processed', 'result' => ['id' => 'synthetic', 'status' => 'active']]);
    }

    public function test_empty_job_envelope_requires_a_separate_detail_read(): void
    {
        $result = app(ServiceRequestApi::class)->validateJobResult(['status' => 'processed', 'response_data' => ['data' => []]]);

        $this->assertArrayNotHasKey('response_data', $result);
    }

    public function test_multiple_job_resources_are_rejected(): void
    {
        $first = Arr::toSnakeCase(json_decode(file_get_contents(base_path('tests/Fixtures/ServiceRequest/resource.json')), true, flags: JSON_THROW_ON_ERROR));
        $first['subject'] = ['display_value' => 'Synthetic patient'];
        $second = array_replace($first, ['id' => '10050000-0000-4000-8000-000000000999']);
        $api = app(ServiceRequestApi::class);
        foreach ([$first, $second] as $resource) {
            $this->assertSame($resource, $api->validateResource($resource));
        }
        $this->expectException(ValidationException::class);
        $api->validateJobResult(['status' => 'processed', 'result' => [$first, $second]]);
    }

    public function test_complete_embedded_resource_keeps_zero_and_unknown_extensions(): void
    {
        $resource = Arr::toSnakeCase(json_decode(file_get_contents(base_path('tests/Fixtures/ServiceRequest/resource.json')), true, flags: JSON_THROW_ON_ERROR));
        $resource['subject'] = ['display_value' => 'Synthetic patient'];
        $resource['remote_extension'] = ['flag' => false, 'empty' => []];
        $actual = app(ServiceRequestApi::class)->validateJobResult(['status' => 'processed', 'result' => ['data' => $resource]]);

        $this->assertSame($resource, $actual['response_data']);
        $this->assertSame(0, $actual['response_data']['remaining_quantity']['value']);
    }
}
