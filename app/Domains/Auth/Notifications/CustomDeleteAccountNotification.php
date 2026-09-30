<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Auth\Mail\DeleteAccountEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class CustomDeleteAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $displayName,
        public readonly string $email,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return (new DeleteAccountEmail(displayName: $this->displayName))->to($this->email);
    }
}
