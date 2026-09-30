<?php

declare(strict_types=1);

namespace App\Domains\Auth\Events;

use App\Domains\Auth\Entities\User;

class UserRegistered
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $afterVerificationRedirectUrl = null,
    ) {}
}
