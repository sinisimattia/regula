<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Auth\Mail\ResetPasswordEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class CustomResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly string $passwordResetPageUrl,
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
        $separator = str_contains($this->passwordResetPageUrl, '?') ? '&' : '?';
        $resetUrl = $this->passwordResetPageUrl . $separator . http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]);

        return (new ResetPasswordEmail($resetUrl))->to($this->email);
    }
}
