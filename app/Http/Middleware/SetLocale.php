<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User as UserModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var UserModel|null $userModel */
        $userModel = $request->user();

        if ($userModel === null) {
            return $next($request);
        }

        $language = $userModel->preferred_language;

        app()->setLocale($language);

        return $next($request);
    }
}
