<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http\Middleware;

use App\Http\Middleware\Authenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\UnitTestCase;

class AuthenticateTest extends UnitTestCase
{
    #[Test]
    public function redirectTo_returns_null(): void
    {
        $middleware = new Authenticate(app('auth'));

        $request = new Request();

        $reflection = new ReflectionClass($middleware);
        $method = $reflection->getMethod('redirectTo');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, $request);

        $this->assertNull($result);
    }

    #[Test]
    public function handle_throws_authentication_exception_for_unauthenticated_request(): void
    {
        $middleware = new Authenticate(app('auth'));

        $request = new Request();
        $request->setUserResolver(function () {
            return null;
        });

        $this->expectException(AuthenticationException::class);

        $middleware->handle($request, function () {
            return response()->noContent();
        });
    }

    #[Test]
    public function handle_allows_authenticated_request_to_proceed(): void
    {
        $user = \App\Models\User::factory()->make();

        $middleware = new Authenticate(app('auth'));

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        auth()->setUser($user);

        $response = $middleware->handle($request, function () {
            return response()->noContent();
        });

        $this->assertTrue($response->isSuccessful());
    }
}
