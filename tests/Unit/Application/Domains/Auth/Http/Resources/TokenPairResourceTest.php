<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Http\Resources;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Http\Resources\TokenPairResource;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for TokenPairResource:
 * - to_array_exposes_both_tokens_the_expiry_as_a_unix_timestamp_and_verification
 */
class TokenPairResourceTest extends UnitTestCase
{
    #[Test]
    public function to_array_exposes_both_tokens_the_expiry_as_a_unix_timestamp_and_verification(): void
    {
        $resource = new TokenPairResource(new TokenPair(
            user: new User(id: new UserId(1), emailVerifiedAt: new DateTimeImmutable('2026-01-01 00:00:00')),
            accessToken: 'access',
            refreshToken: 'refresh',
            accessTokenExpiresAt: new DateTimeImmutable('@1767225600'),
        ));

        $this->assertSame([
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'token_expires_at' => 1767225600,
            'has_verified_email' => true,
        ], $resource->toArray(new Request()));
    }
}
