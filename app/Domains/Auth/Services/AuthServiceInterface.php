<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Exceptions\UserNotFoundException;

interface AuthServiceInterface
{
    /**
     * An unknown email, a wrong password and an account pending deletion fail identically and in
     * the same time, so a caller cannot learn which addresses hold an account.
     *
     * @throws InvalidCredentialsException
     */
    public function loginWithCredentials(string $email, string $password, bool $rememberMe): TokenPair;

    /**
     * Revokes every token the user holds and issues a fresh pair.
     *
     * @throws UserNotFoundException
     * @throws InvalidCredentialsException when the account is pending deletion
     */
    public function issueTokens(UserId $userId, bool $rememberMe): TokenPair;

    /**
     * Issues a fresh pair with the same lifetime as the pair it replaces: a remembered session
     * stays remembered, and a standard one can never be stretched.
     *
     * @throws UserNotFoundException
     * @throws InvalidCredentialsException when the account is pending deletion
     */
    public function refreshTokens(UserId $userId): TokenPair;

    /**
     * @throws UserNotFoundException
     */
    public function logout(UserId $userId): void;

    /**
     * Succeeds silently for an unknown email, an account pending deletion or a throttled repeat,
     * so the answer never reveals whether an account exists.
     */
    public function sendPasswordResetLink(string $email, string $passwordResetPageUrl): void;

    /**
     * @throws PasswordResetFailedException
     */
    public function resetUserPassword(string $email, string $password, string $token): void;
}
