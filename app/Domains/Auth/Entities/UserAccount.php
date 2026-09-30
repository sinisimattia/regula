<?php

declare(strict_types=1);

namespace App\Domains\Auth\Entities;

use DateTimeImmutable;

/**
 * A user together with the credentials and preferences only the Auth domain handles.
 */
class UserAccount
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $hashedPassword = null,
        public readonly ?string $preferredLanguage = null,
        public readonly ?string $timezone = null,
        public readonly ?DateTimeImmutable $deletionRequestedAt = null,
    ) {}

    public function withHashedPassword(string $hashedPassword): self
    {
        return new self(
            user: $this->user,
            firstName: $this->firstName,
            lastName: $this->lastName,
            hashedPassword: $hashedPassword,
            preferredLanguage: $this->preferredLanguage,
            timezone: $this->timezone,
            deletionRequestedAt: $this->deletionRequestedAt,
        );
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletionRequestedAt !== null;
    }
}
