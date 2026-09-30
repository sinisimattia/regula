<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\InvalidVerificationLinkException;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Domains\Auth\Http\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Http\Requests\LoginRequest;
use App\Domains\Auth\Http\Requests\RegisterRequest;
use App\Domains\Auth\Http\Requests\ResetPasswordRequest;
use App\Domains\Auth\Http\Requests\SendEmailVerificationNotificationRequest;
use App\Domains\Auth\Http\Resources\TokenPairResource;
use App\Domains\Auth\Services\AccountServiceInterface;
use App\Domains\Auth\Services\AuthServiceInterface;
use App\Exceptions\HttpApplicationException;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccountServiceInterface $accountService,
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * @throws HttpApplicationException
     */
    public function register(RegisterRequest $request): TokenPairResource
    {
        try {
            $tokenPair = $this->accountService->registerUser(
                account: $request->extractEntity(),
                password: (string) $request->input('password'),
                afterVerificationRedirectUrl: $request->input('after_verification_redirect_url'),
            );
        } catch (UserAlreadyExistsException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_UNPROCESSABLE_ENTITY,
                message: 'User with this email already exists.',
                previous: $e,
            );
        }

        return new TokenPairResource($tokenPair);
    }

    /**
     * @throws HttpApplicationException
     */
    public function login(LoginRequest $request): TokenPairResource
    {
        try {
            $tokenPair = $this->authService->loginWithCredentials(
                email: (string) $request->input('email'),
                password: (string) $request->input('password'),
                rememberMe: $request->boolean('remember_me'),
            );
        } catch (InvalidCredentialsException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_UNAUTHORIZED,
                message: 'Invalid credentials.',
                previous: $e,
            );
        }

        return new TokenPairResource($tokenPair);
    }

    /**
     * @throws HttpApplicationException
     */
    public function refreshToken(Request $request): TokenPairResource
    {
        try {
            $tokenPair = $this->authService->refreshTokens(userId: $this->authenticatedUserId($request));
        } catch (UserNotFoundException $e) {
            throw $this->authenticatedUserVanished($e);
        } catch (InvalidCredentialsException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_UNAUTHORIZED,
                message: 'Invalid credentials.',
                previous: $e,
            );
        }

        return new TokenPairResource($tokenPair);
    }

    /**
     * @throws HttpApplicationException
     */
    public function logout(Request $request): Response
    {
        try {
            $this->authService->logout(userId: $this->authenticatedUserId($request));
        } catch (UserNotFoundException $e) {
            throw $this->authenticatedUserVanished($e);
        }

        return response()->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request): Response
    {
        $this->authService->sendPasswordResetLink(
            email: (string) $request->input('email'),
            passwordResetPageUrl: (string) $request->input('password_reset_page_url'),
        );

        return response()->noContent();
    }

    /**
     * @throws HttpApplicationException
     */
    public function resetPassword(ResetPasswordRequest $request): Response
    {
        try {
            $this->authService->resetUserPassword(
                email: (string) $request->input('email'),
                password: (string) $request->input('password'),
                token: (string) $request->input('token'),
            );
        } catch (PasswordResetFailedException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_UNPROCESSABLE_ENTITY,
                message: 'Password reset failed.',
                previous: $e,
            );
        }

        return response()->noContent();
    }

    /**
     * @throws HttpApplicationException
     */
    public function sendEmailVerificationNotification(SendEmailVerificationNotificationRequest $request): Response
    {
        try {
            $this->accountService->sendEmailVerificationNotification(
                userId: $this->authenticatedUserId($request),
                afterVerificationRedirectUrl: $request->input('after_verification_redirect_url'),
            );
        } catch (UserNotFoundException $e) {
            throw $this->authenticatedUserVanished($e);
        }

        return response()->noContent();
    }

    /**
     * @throws HttpApplicationException
     */
    public function verifyAccount(Request $request, int $userId, string $hash): Response|RedirectResponse
    {
        try {
            $this->accountService->verifyEmail(userId: new UserId($userId), hash: $hash);
        } catch (UserNotFoundException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_NOT_FOUND,
                message: 'User not found.',
                previous: $e,
            );
        } catch (InvalidVerificationLinkException $e) {
            throw new HttpApplicationException(
                code: Response::HTTP_UNPROCESSABLE_ENTITY,
                message: 'Invalid verification link.',
                previous: $e,
            );
        }

        // Safe to follow: the URL is part of the signed link, so only a validated one reaches here
        $redirectUrl = $request->query('redirect_url');

        if (is_string($redirectUrl) && filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            return redirect($redirectUrl);
        }

        return response()->noContent();
    }

    /**
     * @throws HttpApplicationException
     */
    public function deleteAccount(Request $request): Response
    {
        try {
            $this->accountService->requestAccountDeletion(userId: $this->authenticatedUserId($request));
        } catch (UserNotFoundException $e) {
            throw $this->authenticatedUserVanished($e);
        }

        return response()->noContent();
    }

    private function authenticatedUserId(Request $request): UserId
    {
        /** @var Authenticatable $authenticatable */
        $authenticatable = $request->user();

        return new UserId($authenticatable->getAuthIdentifier());
    }

    private function authenticatedUserVanished(UserNotFoundException $exception): HttpApplicationException
    {
        return new HttpApplicationException(
            code: Response::HTTP_UNAUTHORIZED,
            message: 'The authenticated account no longer exists.',
            previous: $exception,
        );
    }
}
