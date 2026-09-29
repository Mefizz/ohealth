<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\EHealthLoginController;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ResolveLoginScopesTest extends TestCase
{
    #[Test]
    public function single_role_session_keeps_oauth_scopes_only(): void
    {
        Session::put('first_login_role', 'DOCTOR');

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldNotReceive('getPermissionsViaRoles');
        $user->shouldNotReceive('unsetRelation');

        $scopes = $this->invokeResolveLoginScopes($user, [
            'employee:read',
            'employee:read',
            '',
            'declaration:read',
        ]);

        $this->assertSame(['employee:read', 'declaration:read'], $scopes);
    }

    #[Test]
    public function without_single_role_session_merges_permissions_via_roles(): void
    {
        Session::forget('first_login_role');

        $viaRoles = new Collection([
            (object) ['name' => 'employee_request:read'],
            (object) ['name' => 'employee:write'],
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('unsetRelation')->twice()->andReturnSelf();
        $user->shouldReceive('getPermissionsViaRoles')->once()->andReturn($viaRoles);

        $scopes = $this->invokeResolveLoginScopes($user, [
            'employee:read',
            'employee_request:read',
        ]);

        $this->assertEqualsCanonicalizing(
            ['employee:read', 'employee_request:read', 'employee:write'],
            $scopes
        );
    }

    /**
     * @param  list<string>  $oauthScopes
     * @return list<string>
     */
    private function invokeResolveLoginScopes(User $user, array $oauthScopes): array
    {
        $controller = new EHealthLoginController();
        $method = new ReflectionMethod($controller, 'resolveLoginScopes');

        return $method->invoke($controller, $user, $oauthScopes);
    }
}
