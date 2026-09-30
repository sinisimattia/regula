<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Domains\Auth\Rules;

use App\Domains\Auth\Rules\AllowedRedirectUrlRule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Tests for AllowedRedirectUrlRule:
 * - accepts_a_url_on_an_allowed_origin
 * - rejects_a_url_off_every_allowed_origin
 */
class AllowedRedirectUrlRuleTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.allowed_redirect_origins', ['https://app.example.com', 'http://localhost:4200/']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedUrls(): array
    {
        return [
            'a path on the first origin' => ['https://app.example.com/reset-password'],
            'a query on the first origin' => ['https://app.example.com/welcome?from=email'],
            'uppercase host' => ['https://APP.example.com/reset'],
            'an origin configured with a trailing slash' => ['http://localhost:4200/reset'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedUrls(): array
    {
        return [
            'another host' => ['https://evil.example/reset'],
            'a lookalike subdomain' => ['https://app.example.com.evil.example/reset'],
            'the right host over http' => ['http://app.example.com/reset'],
            'the right host on another port' => ['https://app.example.com:8443/reset'],
            'userinfo pointing elsewhere' => ['https://app.example.com@evil.example/reset'],
            'a backslash hiding another host' => ['https://evil.example\\@app.example.com/reset'],
            'userinfo in front of the right host' => ['https://someone@app.example.com/reset'],
            'a password in front of the right host' => ['https://someone:secret@app.example.com/reset'],
            'a relative path' => ['/reset'],
        ];
    }

    #[Test]
    #[DataProvider('allowedUrls')]
    public function accepts_a_url_on_an_allowed_origin(string $url): void
    {
        $this->assertTrue($this->passes($url));
    }

    #[Test]
    #[DataProvider('refusedUrls')]
    public function rejects_a_url_off_every_allowed_origin(string $url): void
    {
        $this->assertFalse($this->passes($url));
    }

    private function passes(string $url): bool
    {
        return Validator::make(['url' => $url], ['url' => [new AllowedRedirectUrlRule()]])->passes();
    }
}
