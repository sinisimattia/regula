<?php

declare(strict_types=1);

namespace App\Domains\Auth\Repositories;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Exceptions\PasswordResetThrottledException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use DateTimeImmutable;

interface UserRepositoryInterface
{
    /**
     * @throws UserAlreadyExistsException when another account already holds the email
     * @throws UserNotFoundException when the account carries an id that no longer exists
     */
    public function storeUserAccount(UserAccount $account): UserAccount;

    /**
     * @throws UserNotFoundException
     */
    public function getUserById(UserId $userId): User;

    /**
     * @throws UserNotFoundException
     */
    public function getUserAccountById(UserId $userId): UserAccount;

    /**
     * Emails match case-insensitively: they are stored and looked up in lower case.
     *
     * @throws UserNotFoundException
     */
    public function getUserAccountByEmail(string $email): UserAccount;

    /**
     * @throws UserNotFoundException
     */
    public function deleteUser(UserId $userId): void;

    /**
     * @throws UserNotFoundException
     */
    public function markEmailAsVerified(UserId $userId): void;

    /**
     * @throws UserNotFoundException
     */
    public function markDeletionRequested(UserId $userId): void;

    /**
     * @throws UserNotFoundException
     */
    public function recordLogin(UserId $userId): void;

    /**
     * Replaces every token the user holds with a fresh access and refresh pair. A remembered pair
     * is recognised later by {@see self::hasRememberedRefreshToken()}.
     *
     * @throws UserNotFoundException
     */
    public function issueTokenPair(
        UserId $userId,
        DateTimeImmutable $accessTokenExpiresAt,
        DateTimeImmutable $refreshTokenExpiresAt,
        bool $remembered,
    ): TokenPair;

    /**
     * @throws UserNotFoundException
     */
    public function hasRememberedRefreshToken(UserId $userId): bool;

    /**
     * @throws UserNotFoundException
     */
    public function deleteTokens(UserId $userId): void;

    /**
     * @throws UserNotFoundException
     */
    public function detachRolesAndPermissionsFromUser(UserId $userId): void;

    /**
     * @throws UserNotFoundException
     * @throws PasswordResetThrottledException when a token was issued to the user moments ago
     */
    public function storePasswordResetToken(UserId $userId): string;

    /**
     * Sets the new password, revokes every token and consumes the reset token.
     *
     * @throws UserNotFoundException
     * @throws PasswordResetFailedException when the reset token is invalid or expired
     */
    public function resetUserPassword(UserId $userId, string $password, string $token): void;
}
