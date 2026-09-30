<?php

declare(strict_types=1);

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Services\AccountServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteUserAccountOnUserDeletedListener implements ShouldQueue
{
    public int $tries = 5;

    /**
     * Seconds between attempts, capped at 900 because SQS rejects a delay above 15 minutes.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 900];

    public function __construct(private readonly AccountServiceInterface $accountService) {}

    public function handle(UserDeleted $event): void
    {
        $this->accountService->deleteUserAccount(user: $event->user);
    }
}
