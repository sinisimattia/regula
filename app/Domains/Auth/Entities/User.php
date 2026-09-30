<?php

declare(strict_types=1);

namespace App\Domains\Auth\Entities;

use DateTimeImmutable;

class User
{
    public function __construct(
        public readonly ?UserId $id = null,
        public readonly ?string $displayName = null,
        public readonly ?string $email = null,
        public readonly ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
