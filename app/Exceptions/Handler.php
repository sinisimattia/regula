<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($this->shouldNotWrap($e)) {
                return null;
            }

            return (new HttpApplicationException(
                code: Response::HTTP_INTERNAL_SERVER_ERROR,
                message: 'An unexpected error occurred.',
                previous: $e,
            ))->render($request);
        });
    }

    /**
     * Answers a JSON 401 rather than Laravel's redirect to `route('login')`, which does not exist
     * here; a redirect the exception carries itself (the Filament panel's login) is still followed.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|RedirectResponse
    {
        $redirect = $exception->redirectTo($request);

        if ($redirect !== null && !$this->shouldReturnJson($request, $exception)) {
            return redirect()->guest($redirect);
        }

        return (new HttpApplicationException(
            code: Response::HTTP_UNAUTHORIZED,
            message: $exception->getMessage(),
        ))->render($request);
    }

    private function shouldNotWrap(Throwable $e): bool
    {
        return $e instanceof HttpApplicationException
            || $e instanceof HttpExceptionInterface
            || $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException;
    }
}
