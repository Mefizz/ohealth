<?php

declare(strict_types=1);

namespace Tests\Unit\Employee;

use App\Classes\eHealth\Api\EmployeeRequest as EmployeeRequestApi;
use App\Classes\eHealth\EHealthResponse;
use App\Enums\Employee\RequestStatus;
use App\Listeners\eHealth\EmployeeCreate;
use App\Models\Employee\EmployeeRequest;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class EmployeeCreateRemoteApprovalGateTest extends TestCase
{
    #[Test]
    public function remote_approval_gate_returns_false_for_new_status(): void
    {
        $request = new EmployeeRequest([
            'uuid' => (string) Str::uuid(),
            'status' => RequestStatus::NEW,
        ]);

        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('validate')->once()->andReturn([
            'uuid' => $request->uuid,
            'status' => 'NEW',
        ]);

        $api = Mockery::mock(EmployeeRequestApi::class);
        $api->shouldReceive('getDetails')->once()->with($request->uuid)->andReturn($response);
        $this->instance(EmployeeRequestApi::class, $api);

        $listener = new EmployeeCreate();
        $method = new ReflectionMethod(EmployeeCreate::class, 'isRemoteEmployeeRequestApproved');

        $this->assertFalse($method->invoke($listener, $request));
    }

    #[Test]
    public function remote_approval_gate_returns_true_for_approved_status(): void
    {
        $request = new EmployeeRequest([
            'uuid' => (string) Str::uuid(),
            'status' => RequestStatus::NEW,
        ]);

        $response = Mockery::mock(EHealthResponse::class);
        $response->shouldReceive('validate')->once()->andReturn([
            'uuid' => $request->uuid,
            'status' => 'APPROVED',
        ]);

        $api = Mockery::mock(EmployeeRequestApi::class);
        $api->shouldReceive('getDetails')->once()->with($request->uuid)->andReturn($response);
        $this->instance(EmployeeRequestApi::class, $api);

        $listener = new EmployeeCreate();
        $method = new ReflectionMethod(EmployeeCreate::class, 'isRemoteEmployeeRequestApproved');

        $this->assertTrue($method->invoke($listener, $request));
    }

    #[Test]
    public function remote_approval_gate_returns_false_without_uuid(): void
    {
        $request = new EmployeeRequest([
            'status' => RequestStatus::NEW,
        ]);

        $api = Mockery::mock(EmployeeRequestApi::class);
        $api->shouldNotReceive('getDetails');
        $this->instance(EmployeeRequestApi::class, $api);

        $listener = new EmployeeCreate();
        $method = new ReflectionMethod(EmployeeCreate::class, 'isRemoteEmployeeRequestApproved');

        $this->assertFalse($method->invoke($listener, $request));
    }
}
