<?php

declare(strict_types=1);

namespace App\Domains\Auth\Notifications;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Mail\VerificationEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CustomVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly ?string $afterVerificationRedirectUrl = null,
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
        return (new VerificationEmail($this->verificationUrl()))->to($this->user->email);
    }

    private function verificationUrl(): string
    {
        $parameters = [
            'userId' => $this->user->id->value,
            'hash' => sha1((string) $this->user->email),
        ];

        if ($this->afterVerificationRedirectUrl !== null) {
            $parameters['redirect_url'] = $this->afterVerificationRedirectUrl;
        }

        return URL::temporarySignedRoute(
            name: 'verification.verify',
            expiration: now()->addMinutes(config('auth.verification.expire', 60)),
            parameters: $parameters,
        );
    }
}
