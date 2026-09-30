<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Notifications;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Mail\VerificationEmail;
use App\Domains\Auth\Notifications\CustomVerificationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for CustomVerificationNotification:
 * - to_mail_addresses_the_user_with_a_signed_link_to_verify_them
 * - to_mail_carries_the_redirect_inside_the_signed_link
 * - is_queued
 */
class CustomVerificationNotificationTest extends UnitTestCase
{
    #[Test]
    public function to_mail_addresses_the_user_with_a_signed_link_to_verify_them(): void
    {
        $notification = new CustomVerificationNotification(
            user: new User(id: new UserId(123), email: 'jane@example.com'),
        );

        $mailable = $notification->toMail(new AnonymousNotifiable());

        $this->assertInstanceOf(VerificationEmail::class, $mailable);
        $this->assertSame('jane@example.com', $mailable->to[0]['address']);

        $request = Request::create($mailable->verificationUrl);
        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertStringContainsString('/verify/123/' . sha1('jane@example.com'), $mailable->verificationUrl);
        $this->assertNull($request->query('redirect_url'));
    }

    #[Test]
    public function to_mail_carries_the_redirect_inside_the_signed_link(): void
    {
        $notification = new CustomVerificationNotification(
            user: new User(id: new UserId(123), email: 'jane@example.com'),
            afterVerificationRedirectUrl: 'http://localhost:4200/welcome',
        );

        $mailable = $notification->toMail(new AnonymousNotifiable());

        $request = Request::create($mailable->verificationUrl);
        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertSame('http://localhost:4200/welcome', $request->query('redirect_url'));
    }

    #[Test]
    public function is_queued(): void
    {
        $notification = new CustomVerificationNotification(user: new User(id: new UserId(123), email: 'jane@example.com'));

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }
}
