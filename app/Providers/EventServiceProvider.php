<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Listeners\DeleteUserAccountOnUserDeletedListener;
use App\Domains\Auth\Listeners\SendVerificationEmailOnUserRegisteredListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendVerificationEmailOnUserRegisteredListener::class,
        ],
        UserDeleted::class => [
            DeleteUserAccountOnUserDeletedListener::class,
        ],
    ];

    protected $subscribe = [];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
