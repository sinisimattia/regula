<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Exceptions;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\FeatureTestCase;

/**
 * A failed authentication must answer 401, whatever the request asks to receive.
 *
 * {@see \App\Exceptions\Handler::unauthenticated()} explains why that needed an override.
 */
class UnauthenticatedResponseTest extends FeatureTestCase
{
    #[Test]
    public function protected_route_returns_401_when_the_request_expects_json(): void
    {
        $this->postJson(route('auth.logout'))
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonPath('code', Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function protected_route_returns_401_when_the_request_does_not_expect_json(): void
    {
        // The regression: without this header the framework tried to resolve the
        // `login` route name and raised a 500 instead of rejecting the request.
        $this->post(route('auth.logout'), [], ['Accept' => 'text/html'])
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function unauthenticated_response_uses_the_standard_error_envelope(): void
    {
        $this->postJson(route('auth.logout'))
            ->assertJsonStructure(['code', 'error_key', 'message', 'additional_info']);
    }

    #[Test]
    public function the_login_route_is_registered_under_the_auth_prefix(): void
    {
        // Pins the cause. If someone ever registers a bare `login` route the
        // handler override stops being load-bearing, and this says so out loud.
        $this->assertTrue(app('router')->has('auth.login'));
        $this->assertFalse(app('router')->has('login'));
    }
}
