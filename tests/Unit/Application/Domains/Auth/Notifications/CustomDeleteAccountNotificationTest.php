<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Notifications;

use App\Domains\Auth\Mail\DeleteAccountEmail;
use App\Domains\Auth\Notifications\CustomDeleteAccountNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for CustomDeleteAccountNotification:
 * - via_returns_mail_channel
 * - to_mail_addresses_the_user_by_name
 * - is_queued
 */
class CustomDeleteAccountNotificationTest extends UnitTestCase
{
    #[Test]
    public function via_returns_mail_channel(): void
    {
        $notification = new CustomDeleteAccountNotification(displayName: 'Jane Smith', email: 'jane@example.com');

        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    #[Test]
    public function to_mail_addresses_the_user_by_name(): void
    {
        $notification = new CustomDeleteAccountNotification(displayName: 'Jane Smith', email: 'jane@example.com');

        $mailable = $notification->toMail(new AnonymousNotifiable());

        $this->assertInstanceOf(DeleteAccountEmail::class, $mailable);
        $this->assertSame('jane@example.com', $mailable->to[0]['address']);
        $this->assertSame('Jane Smith', $mailable->displayName);
    }

    #[Test]
    public function is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new CustomDeleteAccountNotification(displayName: 'Jane Smith', email: 'jane@example.com'));
    }
}
