<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Listeners;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Listeners\SendVerificationEmailOnUserRegisteredListener;
use App\Domains\Auth\Services\AccountServiceInterface;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for SendVerificationEmailOnUserRegisteredListener:
 * - handle_asks_the_service_to_verify_the_registered_user_with_the_redirect
 * - handle_passes_a_missing_redirect_through_as_null
 */
class SendVerificationEmailOnUserRegisteredListenerTest extends UnitTestCase
{
    #[Test]
    public function handle_asks_the_service_to_verify_the_registered_user_with_the_redirect(): void
    {
        [$capturedUserId, $capturedRedirectUrl] = $this->handle(new UserRegistered(
            user: new User(id: new UserId(42), email: 'jane@example.com'),
            afterVerificationRedirectUrl: 'http://localhost:4200/welcome',
        ));

        $this->assertSame(42, $capturedUserId?->value);
        $this->assertSame('http://localhost:4200/welcome', $capturedRedirectUrl);
    }

    #[Test]
    public function handle_passes_a_missing_redirect_through_as_null(): void
    {
        [$capturedUserId, $capturedRedirectUrl] = $this->handle(new UserRegistered(
            user: new User(id: new UserId(42), email: 'jane@example.com'),
        ));

        $this->assertSame(42, $capturedUserId?->value);
        $this->assertNull($capturedRedirectUrl);
    }

    /**
     * @return array{0: UserId|null, 1: string|null}
     */
    private function handle(UserRegistered $event): array
    {
        $capturedUserId = null;
        $capturedRedirectUrl = 'not called';

        $accountService = Mockery::mock(AccountServiceInterface::class);
        $accountService->shouldReceive('sendEmailVerificationNotification')
            ->once()
            ->withArgs(function (UserId $userId, ?string $afterVerificationRedirectUrl) use (&$capturedUserId, &$capturedRedirectUrl): bool {
                $capturedUserId = $userId;
                $capturedRedirectUrl = $afterVerificationRedirectUrl;

                return true;
            });

        (new SendVerificationEmailOnUserRegisteredListener($accountService))->handle($event);

        return [$capturedUserId, $capturedRedirectUrl];
    }
}
