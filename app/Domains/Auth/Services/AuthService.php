<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Exceptions\PasswordResetThrottledException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Domains\Auth\Notifications\CustomResetPasswordNotification;
use App\Domains\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class AuthService implements AuthServiceInterface
{
    /**
     * A bcrypt hash at the default cost, checked and discarded on an unknown email so the request
     * takes as long as a known one.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$ILkuqzrHzoiNELpvQQw5ruM8l1DDkdPFHZBubUf808y.yiQV5618a';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function loginWithCredentials(string $email, string $password, bool $rememberMe): TokenPair
    {
        try {
            $account = $this->userRepository->getUserAccountByEmail(email: $email);
        } catch (UserNotFoundException $e) {
            Hash::check($password, self::TIMING_EQUALISER_HASH);

            throw new InvalidCredentialsException(previous: $e);
        }

        if ($account->hashedPassword === null || !Hash::check($password, $account->hashedPassword)) {
            throw new InvalidCredentialsException();
        }

        if ($account->isPendingDeletion()) {
            throw new InvalidCredentialsException();
        }

        $this->userRepository->recordLogin(userId: $account->user->id);

        return $this->issueTokens(userId: $account->user->id, rememberMe: $rememberMe);
    }

    public function issueTokens(UserId $userId, bool $rememberMe): TokenPair
    {
        if ($this->userRepository->getUserAccountById(userId: $userId)->isPendingDeletion()) {
            throw new InvalidCredentialsException();
        }

        $accessTokenExpiresAt = now()->addMinutes(config('sanctum.access_token_expiration'));
        $refreshTokenExpiresAt = now()->addMinutes(config('sanctum.refresh_token_expiration'));

        if ($rememberMe) {
            $accessTokenExpiresAt = now()->addDay();
            $refreshTokenExpiresAt = now()->addYear();
        }

        return $this->userRepository->issueTokenPair(
            userId: $userId,
            accessTokenExpiresAt: $accessTokenExpiresAt->toDateTimeImmutable(),
            refreshTokenExpiresAt: $refreshTokenExpiresAt->toDateTimeImmutable(),
            remembered: $rememberMe,
        );
    }

    public function refreshTokens(UserId $userId): TokenPair
    {
        return $this->issueTokens(
            userId: $userId,
            rememberMe: $this->userRepository->hasRememberedRefreshToken(userId: $userId),
        );
    }

    public function logout(UserId $userId): void
    {
        $this->userRepository->deleteTokens(userId: $userId);
    }

    public function sendPasswordResetLink(string $email, string $passwordResetPageUrl): void
    {
        try {
            $account = $this->userRepository->getUserAccountByEmail(email: $email);

            if ($account->isPendingDeletion()) {
                return;
            }

            $token = $this->userRepository->storePasswordResetToken(userId: $account->user->id);
        } catch (UserNotFoundException|PasswordResetThrottledException) {
            return;
        }

        Notification::route('mail', $account->user->email)->notify(new CustomResetPasswordNotification(
            token: $token,
            email: $account->user->email,
            passwordResetPageUrl: $passwordResetPageUrl,
        ));
    }

    public function resetUserPassword(string $email, string $password, string $token): void
    {
        try {
            $account = $this->userRepository->getUserAccountByEmail(email: $email);
        } catch (UserNotFoundException $e) {
            throw new PasswordResetFailedException(status: Password::INVALID_USER, previous: $e);
        }

        $this->userRepository->resetUserPassword(userId: $account->user->id, password: $password, token: $token);
    }
}
