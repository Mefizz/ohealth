<?php

declare(strict_types=1);

namespace Tests\Feature\Referral;

use App\Classes\eHealth\Api\MedicalEvents\ServiceRequest;
use App\Classes\eHealth\EHealthResponse;
use App\Dto\ServiceRequest\EhealthPrequalify;
use App\Exceptions\EHealth\EHealthValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class ServiceRequestPrequalifyTest extends TestCase
{
    protected const string PROGRAM = 'a1b2c3d4-0000-4000-8000-000000000001';
    protected const string SECOND_PROGRAM = 'a1b2c3d4-0000-4000-8000-000000000002';
    protected const array URGENT = ['urgent' => ['authentication_method_current' => ['type' => 'OTP', 'number' => '+38093*****85']]];
    protected const array VERDICT = ['program_id' => self::PROGRAM, 'program_name' => 'Test program', 'status' => 'VALID'];

    public function test_prequalification_preserves_transfer_content_with_and_without_a_program(): void
    {
        $service = [
            'category' => ['coding' => [['code' => 'transfer_of_care']]],
            'performer' => ['identifier' => ['value' => 'a1b2c3d4-0000-4000-8000-000000000003']],
        ];
        $content = ['id' => 'a1b2c3d4-0000-4000-8000-000000000004'] + $service;
        $program = ['identifier' => ['value' => self::PROGRAM]];

        foreach ([[], [$program]] as $programs) {
            $source = $programs === [] ? $content : $content + ['program' => $program];
            $payload = objectMapper()->map(new EhealthPrequalify($source));
            $this->assertSame([
                'service_request' => $service + ['status' => 'active', 'intent' => 'order', 'priority' => 'routine'],
                'programs' => $programs,
            ], $payload->all());
        }
    }

    public function test_returns_each_requested_program_verdict_in_response_order(): void
    {
        $verdicts = [
            ['program_id' => strtoupper(self::SECOND_PROGRAM), 'program_name' => 'Another program', 'status' => 'INVALID', 'rejection_reason' => 'Service excluded'],
            self::VERDICT,
        ];
        $response = $this->prequalify([self::PROGRAM, self::SECOND_PROGRAM], self::URGENT + ['data' => $verdicts]);

        $result = $response->validate();
        $this->assertSame($verdicts, $result->all());
        $this->assertSame('Service excluded', $result->rejectionReason());
        $eligible = $this->prequalify([self::PROGRAM], self::URGENT + ['data' => [self::VERDICT]])->validate();
        $this->assertNull($eligible->rejectionReason());
    }

    public function test_no_program_accepts_absent_or_empty_verdict_list(): void
    {
        $this->assertSame([], $this->prequalify([], self::URGENT)->validate()->all());
        $this->assertSame([], $this->prequalify([], self::URGENT + ['data' => []])->validate()->all());
    }

    #[DataProvider('invalidResponses')]
    public function test_rejects_unconfirmed_prequalification(array $programIds, array $body, int $status = 200): void
    {
        $this->expectException(ValidationException::class);
        $this->prequalify($programIds, $body, $status)->validate();
    }

    public static function invalidResponses(): array
    {
        $verdicts = ['data' => [self::VERDICT]];

        return [
            'missing verdict' => [[self::PROGRAM], self::URGENT],
            'unrequested program' => [[self::SECOND_PROGRAM], self::URGENT + $verdicts],
            'duplicate instead of second program' => [[self::PROGRAM, self::SECOND_PROGRAM], self::URGENT + ['data' => [self::VERDICT, self::VERDICT]]],
            'unexpected verdict for empty request' => [[], self::URGENT + $verdicts],
            'empty object instead of list' => [[], self::URGENT + ['data' => new stdClass()]],
            'blank string instead of list' => [[], self::URGENT + ['data' => ' ']],
            'unknown verdict' => [[self::PROGRAM], self::URGENT + ['data' => [array_replace(self::VERDICT, ['status' => 'PENDING'])]]],
            'missing program name' => [[self::PROGRAM], self::URGENT + ['data' => [['status' => 'VALID', 'program_id' => self::PROGRAM]]]],
            'non-string rejection reason' => [[self::PROGRAM], self::URGENT + ['data' => [self::VERDICT + ['rejection_reason' => 1]]]],
            'null data' => [[], self::URGENT + ['data' => null]],
            'missing authentication method' => [[self::PROGRAM], $verdicts],
            'invalid authentication type' => [[], ['urgent' => ['authentication_method_current' => ['type' => 'OTHER']]]],
            'error in success envelope' => [[], self::URGENT + ['error' => ['message' => 'Rejected']]],
            'accepted is not a final response' => [[self::PROGRAM], self::URGENT + $verdicts, 202],
        ];
    }

    public function test_422_keeps_the_ehealth_validation_error(): void
    {
        $this->expectException(EHealthValidationException::class);
        $this->prequalify([self::PROGRAM], ['error' => ['message' => 'Rejected']], 422);
    }

    private function prequalify(array $programIds, array $body, int $status = 200): EHealthResponse
    {
        config()->set('ehealth.api.domain', 'https://ehealth.invalid');
        $api = new ServiceRequest(Http::getFacadeRoot());
        $api->stub([fn () => Http::response($body, $status)])->preventStrayRequests();

        return $api->prequalify('patient-test', [
            'service_request' => [
                'code' => ['identifier' => ['value' => 'a1b2c3d4-0000-4000-8000-000000000003']],
                'requester_employee' => ['identifier' => ['value' => 'a1b2c3d4-0000-4000-8000-000000000004']],
                'requester_legal_entity' => ['identifier' => ['value' => 'a1b2c3d4-0000-4000-8000-000000000005']],
            ],
            'programs' => array_map(fn (string $id): array => ['identifier' => ['value' => $id]], $programIds),
        ]);
    }
}
