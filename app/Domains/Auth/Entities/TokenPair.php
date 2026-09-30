<?php

declare(strict_types=1);

namespace App\Domains\Auth\Entities;

use DateTimeImmutable;

/**
 * The short-lived access token and the longer-lived refresh token issued together to one user.
 */
class TokenPair
{
    public function __construct(
        public readonly User $user,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly DateTimeImmutable $accessTokenExpiresAt,
    ) {}
}
