<?php

declare(strict_types=1);

namespace Tests\Feature\Referral;

use App\Classes\eHealth\Api\Patient\ServiceRequest;
use App\Classes\eHealth\Api\ServiceRequest as ApiServiceRequest;
use App\Core\Arr;
use App\Dto\FormCollection;
use App\Dto\ServiceRequest\Completion\Ehealth as CompletionData;
use App\Dto\ServiceRequest\Resource as ReferralResource;
use App\Dto\ServiceRequest\Status\Ehealth as StatusData;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Livewire\Concerns\ReadsClinicalSnapshots;
use App\Livewire\Referral\Forms\ServiceRequestForm;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Tests\TestCase;

class ServiceRequestFhirApiTest extends TestCase
{
    use ReadsClinicalSnapshots {
        fetchServiceRequestSnapshot as private fetchSignedSnapshot;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ehealth.api.domain', 'https://ehealth.invalid');
        Http::preventStrayRequests();
    }

    public function test_cancel_and_recall_preserve_the_fresh_raw_resource(): void
    {
        $resource = Arr::toSnakeCase($this->resource());
        $resource['remote_extension'] = ['flag' => false, 'empty' => []];
        Http::fake(['*' => Http::response(['data' => $resource])]);
        $api = $this->api();
        $this->instance(ServiceRequest::class, $api);
        $snapshot = $this->fetchSignedSnapshot('patient-test', $resource['id']);
        $form = new ServiceRequestForm(new class extends Component {}, 'serviceRequestForm');
        $form->setStatus($snapshot, 'synthetic-reason', 'Synthetic explanation', 'eHealth/service_request_recall_reasons');
        $actual = (new ObjectMapper())->map($form, StatusData::class)->toArray();
        $this->assertSame(['coding' => [['system' => 'eHealth/service_request_recall_reasons', 'code' => 'synthetic-reason']]], $actual['status_reason']);
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
        $this->instance(ServiceRequest::class, $this->api());
        $this->fetchSignedSnapshot('patient-test', '10050000-0000-4000-8000-000000000999');
    }

    public function test_cancellation_rejects_a_valid_resource_for_a_different_referral(): void
    {
        $resource = Arr::toSnakeCase($this->resource());
        $resource['status_reason'] = ['coding' => [['system' => 'synthetic/reason', 'code' => 'TEST']]];
        Http::fake(['*' => Http::response(['data' => $resource], 201)]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('referrals.errors.submission_mismatch'));
        $this->api()->cancel('patient-test', '10050000-0000-4000-8000-000000000999', [
            'signed_data' => 'synthetic-signature',
            'signed_data_encoding' => 'base64',
        ])->validate();
    }

    public function test_remote_failure_blocks_snapshot_signing(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Synthetic remote failure']], 503)]);
        $this->expectException(EHealthResponseException::class);
        $this->instance(ServiceRequest::class, $this->api());
        $this->fetchSignedSnapshot('patient-test', $this->resource()['id']);
    }

    public function test_snapshot_of_another_patient_is_rejected(): void
    {
        $resource = Arr::toSnakeCase($this->resource());
        $resource['remote_extension'] = ['flag' => false, 'empty' => []];
        $resource['subject']['identifier']['value'] = 'other-patient';
        Http::fake(['*' => Http::response(['data' => $resource])]);
        $this->expectException(ValidationException::class);
        $this->instance(ServiceRequest::class, $this->api());
        $this->fetchSignedSnapshot('patient-test', $resource['id']);
    }

    public function test_empty_explanation_is_rejected_before_signing(): void
    {
        $this->expectException(ValidationException::class);
        $form = new ServiceRequestForm(new class extends Component {}, 'serviceRequestForm');
        $form->setStatus(Arr::toSnakeCase($this->resource()), 'synthetic-reason', '  ', 'synthetic/reasons');
    }

    public function test_create_content_excludes_server_metadata_and_preserves_quantity(): void
    {
        $snapshot = Arr::toSnakeCase($this->resource());
        $snapshot['patient_instruction'] = 'Synthetic instruction';
        $content = (new ReferralResource($snapshot))->createContent();
        $this->assertSame($snapshot['patient_instruction'], $content['patient_instruction']);
        $this->assertSame($snapshot['occurrence_period'], $content['occurrence_period']);
        $this->assertSame(1.23456789, $content['quantity']['value']);
        $this->assertArrayNotHasKey('remaining_quantity', $content);
        $this->assertArrayNotHasKey('remote_extension', $content);
        $this->assertArrayNotHasKey('service_request', $content);
        $this->assertCount(2, $content['based_on']);
    }

    public function test_numeric_local_id_and_remote_uuid_are_distinguished(): void
    {
        $resource = $this->resource();
        $local = new ReferralResource(array_replace($resource, ['id' => 123, 'uuid' => $resource['id']]));
        $this->assertSame($resource['id'], $local->modelData()->uuid);
        $this->assertSame($resource['id'], $local->createContent()['id']);
        $remote = new ReferralResource($resource + ['uuid' => '10050000-0000-4000-8000-000000000999']);
        $this->assertSame($resource['id'], $remote->createContent()['id']);
    }

    public function test_complete_uses_a_single_result_reference_and_keeps_only_allowed_options(): void
    {
        $uuid = '10050000-0000-4000-8000-000000000002';
        foreach (['encounter', 'procedure', 'diagnostic_report'] as $type) {
            $actual = (new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor()))->map(new FormCollection(['resourceUuid' => $uuid, 'resourceType' => $type, 'programService' => ['displayValue' => 'Synthetic service'], 'statusReason' => ['coding' => [['system' => 'synthetic/reason', 'code' => 'TEST']]], 'injected' => 'discard']), CompletionData::class)->toArray();
            $this->assertSame($type, data_get($actual, 'completed_with.identifier.type.coding.0.code'));
            $this->assertSame($uuid, data_get($actual, 'completed_with.identifier.value'));
            $this->assertArrayNotHasKey('based_on', $actual);
            $this->assertArrayNotHasKey('injected', $actual);
            $this->assertArrayHasKey('status_reason', $actual);
            $this->assertSame('Synthetic service', $actual['program_service']['display_value']);
        }
    }

    public function test_signed_status_envelope_does_not_duplicate_fields_outside_the_signature(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'pending']], 202)]);
        $this->api()->recall('patient-test', $this->resource()['id'], [
            'signed_data' => 'immutable-signed-content', 'signed_data_encoding' => 'base64',
            'status_reason' => ['text' => 'Synthetic reason'], 'explanatory_letter' => 'Synthetic letter',
        ]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->data() === ['signed_data' => 'immutable-signed-content', 'signed_data_encoding' => 'base64']);
    }

    public function test_prequalification_rejects_a_verdict_for_another_program(): void
    {
        Http::fake(['*' => Http::response([
            'data' => [['program_id' => '10050000-0000-4000-8000-000000000002', 'program_name' => 'Synthetic', 'status' => 'VALID']],
            'urgent' => ['authentication_method_current' => ['type' => 'OFFLINE']],
        ])]);
        $this->expectException(ValidationException::class);
        $this->api()->prequalify('patient-test', [
            'programs' => [['identifier' => ['value' => '10050000-0000-4000-8000-000000000001']]],
        ])->validate();
    }

    public function test_endpoint_returns_the_validated_pending_receipt_without_polling(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'pending']], 202)]);
        $response = $this->api()->recall('patient-test', $this->resource()['id'], ['signed_data' => 'synthetic-signed-content', 'signed_data_encoding' => 'base64']);
        $this->assertSame('pending', $response->validate()['status']);
        Http::assertSentCount(1);
    }

    public function test_completed_job_uses_the_populated_result_after_empty_envelopes(): void
    {
        $resource = Arr::toSnakeCase($this->resource());
        $job = ['status' => 'processed', 'response_data' => ['data' => []], 'result' => [$resource]];

        $this->assertSame($resource, $this->api()->validateJobResult($job)['response_data']);
    }

    public function test_malformed_pending_receipt_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'active']], 202)]);
        $this->expectException(ValidationException::class);
        $this->api()->recall('patient-test', $this->resource()['id'], ['signed_data' => 'synthetic', 'signed_data_encoding' => 'base64'])->validate();
    }

    public function test_cancel_usage_calls_documented_release_with_no_clinical_cancel_payload(): void
    {
        Http::fake(['*' => Http::response(['data' => ['status' => 'pending', 'job_id' => 'release-job']], 202)]);
        $factory = Http::getFacadeRoot();
        $api = new ApiServiceRequest($factory);
        $stubs = (function () {
            return $this->stubCallbacks;
        })->call($factory);
        $api->stub($stubs)->preventStrayRequests();
        $response = $api->cancelUsage('request-test', 'patient-test', ['signed_data' => 'discard', 'explanatory_letter' => 'local explanation']);
        $this->assertSame('pending', $response->validate()['status']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/api/service_requests/request-test/actions/release') && $request->data() === []);
    }

    private function resource(): array
    {
        $resource = json_decode(file_get_contents(base_path('tests/Fixtures/ServiceRequest/resource.json')), true, flags: JSON_THROW_ON_ERROR);
        $resource['subject'] = ['displayValue' => 'Synthetic patient', 'identifier' => ['value' => 'patient-test', 'type' => ['coding' => [['system' => 'eHealth/resources', 'code' => 'patient']]]]];

        return $resource;
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
