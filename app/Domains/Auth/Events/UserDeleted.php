<?php

declare(strict_types=1);

namespace App\Domains\Auth\Events;

use App\Domains\Auth\Entities\User;

class UserDeleted
{
    public function __construct(public readonly User $user) {}
}
