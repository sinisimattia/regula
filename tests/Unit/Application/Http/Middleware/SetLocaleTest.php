<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http\Middleware;

use App\Http\Middleware\SetLocale;
use App\Models\User as UserModel;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

class SetLocaleTest extends UnitTestCase
{
    #[Test]
    public function handle_sets_locale_from_authenticated_user_preference(): void
    {
        $userModel = UserModel::factory()->make(['preferred_language' => 'es']);

        $request = new Request();
        $request->setUserResolver(function () use ($userModel) {
            return $userModel;
        });

        $middleware = new SetLocale();

        $response = $middleware->handle(
            request: $request,
            next: function () {
                $this->assertEquals('es', app()->getLocale());
                return response()->noContent();
            }
        );

        $this->assertTrue($response->isSuccessful());
    }

    #[Test]
    public function handle_does_not_change_locale_when_no_authenticated_user(): void
    {
        $originalLocale = app()->getLocale();

        $request = new Request();
        $request->setUserResolver(function () {
            return null;
        });

        $middleware = new SetLocale();

        $response = $middleware->handle(
            request: $request,
            next: function () use ($originalLocale) {
                $this->assertEquals($originalLocale, app()->getLocale());
                return response()->noContent();
            }
        );

        $this->assertTrue($response->isSuccessful());
    }

    #[Test]
    public function handle_sets_different_locales_for_different_user_preferences(): void
    {
        $locales = ['en', 'es', 'fr', 'de'];

        foreach ($locales as $locale) {
            $userModel = UserModel::factory()->make(['preferred_language' => $locale]);

            $request = new Request();
            $request->setUserResolver(function () use ($userModel) {
                return $userModel;
            });

            $middleware = new SetLocale();

            $response = $middleware->handle(
                request: $request,
                next: function () use ($locale) {
                    $this->assertEquals($locale, app()->getLocale());
                    return response()->noContent();
                }
            );

            $this->assertTrue($response->isSuccessful());
        }
    }
}
