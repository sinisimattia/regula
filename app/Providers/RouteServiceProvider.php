<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Web routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Core APIs
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Authentication APIs
            Route::middleware([
                'api',
            ])
                ->prefix('api/auth')
                ->as('auth.')
                ->group(base_path('routes/auth.php'));
        })
            ->configureRateLimiting();
    }

    /**
     * @codeCoverageIgnore
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
