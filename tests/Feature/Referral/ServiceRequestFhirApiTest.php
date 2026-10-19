<?php

declare(strict_types=1);

namespace Tests\Feature\Referral;

use App\Classes\eHealth\Api\Patient\ServiceRequest;
use App\Core\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceRequestFhirApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ehealth.api.domain', 'https://ehealth.invalid');
        Http::preventStrayRequests();
    }

    public function test_cancel_and_recall_preserve_the_fresh_raw_resource(): void
    {
        $resource = Arr::toSnakeCase($this->resource());
        Http::fake(['*' => Http::response(['data' => $resource])]);
        $api = $this->api();
        $snapshot = $api->fetchSignedSnapshot('patient-test', $resource['id']);
        $actual = $api->buildStatusContent($snapshot, 'synthetic-reason', 'Synthetic explanation');
        $this->assertSame('synthetic-reason', $actual['status_reason']);
        $this->assertSame('Synthetic explanation', $actual['explanatory_letter']);
        unset($actual['status_reason'], $actual['explanatory_letter']);
        $this->assertSame($resource, $actual);
        $this->assertFalse($actual['remote_extension']['flag']);
        $this->assertSame([], $actual['remote_extension']['empty']);
        $this->assertSame(0, $actual['remaining_quantity']['value']);
    }

    public function test_mismatched_snapshot_identity_blocks_signing(): void
    {
        Http::fake(['*' => Http::response(['data' => Arr::toSnakeCase($this->resource())])]);
        $this->expectException(ValidationException::class);
        $this->api()->fetchSignedSnapshot('patient-test', '10050000-0000-4000-8000-000000000999');
    }

    public function test_empty_explanation_is_rejected_before_signing(): void
    {
        $this->expectException(ValidationException::class);
        $this->api()->buildStatusContent(Arr::toSnakeCase($this->resource()), 'synthetic-reason', '  ');
    }

    public function test_create_content_excludes_server_metadata_and_preserves_quantity(): void
    {
        $content = $this->api()->buildCreateContent($this->resource());
        $this->assertSame(1.23456789, $content['quantity']['value']);
        $this->assertArrayNotHasKey('remaining_quantity', $content);
        $this->assertArrayNotHasKey('remote_extension', $content);
        $this->assertArrayNotHasKey('service_request', $content);
        $this->assertCount(2, $content['based_on']);
    }

    private function resource(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/ServiceRequest/resource.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function api(): ServiceRequest
    {
        $factory = Http::getFacadeRoot();
        $api = new ServiceRequest($factory);
        $stubs = (function () {
            return $this->stubCallbacks;
        })->call($factory);
        $api->stub($stubs)->preventStrayRequests();

        return $api;
    }
}
