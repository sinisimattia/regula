<?php

declare(strict_types=1);

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Services\AccountServiceInterface;

class SendVerificationEmailOnUserRegisteredListener
{
    public function __construct(private readonly AccountServiceInterface $accountService) {}

    public function handle(UserRegistered $event): void
    {
        $this->accountService->sendEmailVerificationNotification(
            userId: $event->user->id,
            afterVerificationRedirectUrl: $event->afterVerificationRedirectUrl,
        );
    }
}
