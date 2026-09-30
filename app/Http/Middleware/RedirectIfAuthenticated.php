<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\HttpApplicationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     * @throws HttpApplicationException
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                throw new HttpApplicationException(
                    code: Response::HTTP_FORBIDDEN,
                    message: 'You are already logged in.',
                );
            }
        }

        return $next($request);
    }
}
