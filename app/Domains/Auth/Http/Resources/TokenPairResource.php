<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Resources;

use App\Domains\Auth\Entities\TokenPair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TokenPair $resource
 */
class TokenPairResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'refresh_token' => $this->resource->refreshToken,
            'token_expires_at' => $this->resource->accessTokenExpiresAt->getTimestamp(),
            'has_verified_email' => $this->resource->user->hasVerifiedEmail(),
        ];
    }
}
