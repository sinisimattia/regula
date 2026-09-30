<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Notifications;

use App\Domains\Auth\Mail\ResetPasswordEmail;
use App\Domains\Auth\Notifications\CustomResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for CustomResetPasswordNotification:
 * - via_returns_mail_channel
 * - to_mail_addresses_the_user_and_links_the_page_with_token_and_email
 * - to_mail_keeps_a_query_string_already_on_the_page_url
 * - is_queued
 */
class CustomResetPasswordNotificationTest extends UnitTestCase
{
    #[Test]
    public function via_returns_mail_channel(): void
    {
        $notification = new CustomResetPasswordNotification(
            token: 'test-token',
            email: 'jane@example.com',
            passwordResetPageUrl: 'http://localhost:4200/reset',
        );

        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    #[Test]
    public function to_mail_addresses_the_user_and_links_the_page_with_token_and_email(): void
    {
        $notification = new CustomResetPasswordNotification(
            token: 'test-token',
            email: 'jane+reset@example.com',
            passwordResetPageUrl: 'http://localhost:4200/reset',
        );

        $mailable = $notification->toMail(new AnonymousNotifiable());

        $this->assertInstanceOf(ResetPasswordEmail::class, $mailable);
        $this->assertSame('jane+reset@example.com', $mailable->to[0]['address']);
        $this->assertSame(
            'http://localhost:4200/reset?token=test-token&email=jane%2Breset%40example.com',
            $mailable->resetUrl,
        );
    }

    #[Test]
    public function to_mail_keeps_a_query_string_already_on_the_page_url(): void
    {
        $notification = new CustomResetPasswordNotification(
            token: 'test-token',
            email: 'jane@example.com',
            passwordResetPageUrl: 'http://localhost:4200/reset?source=email',
        );

        $mailable = $notification->toMail(new AnonymousNotifiable());

        $this->assertSame(
            'http://localhost:4200/reset?source=email&token=test-token&email=jane%40example.com',
            $mailable->resetUrl,
        );
    }

    #[Test]
    public function is_queued(): void
    {
        $notification = new CustomResetPasswordNotification(
            token: 'test-token',
            email: 'jane@example.com',
            passwordResetPageUrl: 'http://localhost:4200/reset',
        );

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }
}
