<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http\Middleware;

use App\Exceptions\HttpApplicationException;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\User as UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\UnitTestCase;

/**
 * Tests for RedirectIfAuthenticated:
 * - handle_allows_guest_request_to_proceed
 * - handle_throws_forbidden_exception_when_default_guard_is_authenticated
 * - handle_throws_forbidden_exception_when_a_named_guard_is_authenticated
 * - handle_allows_request_when_none_of_the_named_guards_are_authenticated
 */
class RedirectIfAuthenticatedTest extends UnitTestCase
{
    #[Test]
    public function handle_allows_guest_request_to_proceed(): void
    {
        $middleware = new RedirectIfAuthenticated();

        $response = $middleware->handle(new Request(), function () {
            return response()->noContent();
        });

        $this->assertTrue($response->isSuccessful());
    }

    #[Test]
    public function handle_throws_forbidden_exception_when_default_guard_is_authenticated(): void
    {
        $user = UserModel::factory()->make();
        Auth::guard()->setUser($user);

        $middleware = new RedirectIfAuthenticated();

        try {
            $middleware->handle(new Request(), function () {
                return response()->noContent();
            });
            $this->fail('Expected an HttpApplicationException to be thrown.');
        } catch (HttpApplicationException $exception) {
            $this->assertSame(Response::HTTP_FORBIDDEN, $exception->code);
            $this->assertSame('You are already logged in.', $exception->message);
        }
    }

    #[Test]
    public function handle_throws_forbidden_exception_when_a_named_guard_is_authenticated(): void
    {
        $user = UserModel::factory()->make();
        Auth::guard('web')->setUser($user);

        $middleware = new RedirectIfAuthenticated();

        $this->expectException(HttpApplicationException::class);

        $middleware->handle(new Request(), function () {
            return response()->noContent();
        }, 'web');
    }

    #[Test]
    public function handle_allows_request_when_none_of_the_named_guards_are_authenticated(): void
    {
        $middleware = new RedirectIfAuthenticated();

        $response = $middleware->handle(new Request(), function () {
            return response()->noContent();
        }, 'web');

        $this->assertTrue($response->isSuccessful());
    }
}
