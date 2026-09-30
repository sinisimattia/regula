<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\InvalidVerificationLinkException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;

interface AccountServiceInterface
{
    /**
     * Creates the account, starts email verification and signs the new user in.
     *
     * @throws UserAlreadyExistsException
     */
    public function registerUser(
        UserAccount $account,
        string $password,
        ?string $afterVerificationRedirectUrl = null,
    ): TokenPair;

    /**
     * Sends nothing when the email is already verified.
     *
     * @throws UserNotFoundException
     */
    public function sendEmailVerificationNotification(UserId $userId, ?string $afterVerificationRedirectUrl = null): void;

    /**
     * A no-op when the email is already verified.
     *
     * @throws UserNotFoundException
     * @throws InvalidVerificationLinkException
     */
    public function verifyEmail(UserId $userId, string $hash): void;

    /**
     * Locks the account out right away (no login, no new tokens, no reset link) and hands it to
     * the deletion pipeline, which removes the row.
     *
     * @throws UserNotFoundException
     */
    public function requestAccountDeletion(UserId $userId): void;

    /**
     * Idempotent: an account that is already gone counts as deleted.
     */
    public function deleteUserAccount(User $user): void;
}
