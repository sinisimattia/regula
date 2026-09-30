<?php

declare(strict_types=1);

namespace App\Domains\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts a URL only when its origin is one of `app.allowed_redirect_origins`.
 */
class AllowedRedirectUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !in_array($this->originOf($value), $this->allowedOrigins(), true)) {
            $fail('The :attribute must point at an allowed application origin.');
        }
    }

    private function originOf(string $url): ?string
    {
        // Browsers read a backslash as a slash and userinfo as noise, where parse_url may not
        if (str_contains($url, '\\')) {
            return null;
        }

        $urlParts = parse_url($url);

        if (!isset($urlParts['scheme'], $urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])) {
            return null;
        }

        $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';

        return strtolower($urlParts['scheme'] . '://' . $urlParts['host'] . $port);
    }

    /**
     * @return array<int, string>
     */
    private function allowedOrigins(): array
    {
        return array_map(
            fn (string $origin): string => strtolower(rtrim($origin, '/')),
            (array) config('app.allowed_redirect_origins', []),
        );
    }
}
