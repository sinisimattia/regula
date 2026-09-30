<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Exceptions\InvalidVerificationLinkException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Domains\Auth\Notifications\CustomDeleteAccountNotification;
use App\Domains\Auth\Notifications\CustomVerificationNotification;
use App\Domains\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class AccountService implements AccountServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuthServiceInterface $authService,
    ) {}

    public function registerUser(
        UserAccount $account,
        string $password,
        ?string $afterVerificationRedirectUrl = null,
    ): TokenPair {
        $storedAccount = $this->userRepository->storeUserAccount(
            account: $account->withHashedPassword(Hash::make($password)),
        );

        event(new UserRegistered(
            user: $storedAccount->user,
            afterVerificationRedirectUrl: $afterVerificationRedirectUrl,
        ));

        return $this->authService->issueTokens(userId: $storedAccount->user->id, rememberMe: false);
    }

    public function sendEmailVerificationNotification(UserId $userId, ?string $afterVerificationRedirectUrl = null): void
    {
        $user = $this->userRepository->getUserById(userId: $userId);

        if ($user->hasVerifiedEmail()) {
            return;
        }

        Notification::route('mail', $user->email)->notify(new CustomVerificationNotification(
            user: $user,
            afterVerificationRedirectUrl: $afterVerificationRedirectUrl,
        ));
    }

    public function verifyEmail(UserId $userId, string $hash): void
    {
        $user = $this->userRepository->getUserById(userId: $userId);

        if (!hash_equals(sha1((string) $user->email), $hash)) {
            throw new InvalidVerificationLinkException();
        }

        if (!$user->hasVerifiedEmail()) {
            $this->userRepository->markEmailAsVerified(userId: $userId);
        }
    }

    public function requestAccountDeletion(UserId $userId): void
    {
        $user = $this->userRepository->getUserById(userId: $userId);

        $this->userRepository->markDeletionRequested(userId: $userId);
        $this->userRepository->deleteTokens(userId: $userId);
        $this->userRepository->detachRolesAndPermissionsFromUser(userId: $userId);

        event(new UserDeleted(user: $user));

        Notification::route('mail', $user->email)->notify(new CustomDeleteAccountNotification(
            displayName: (string) $user->displayName,
            email: (string) $user->email,
        ));
    }

    public function deleteUserAccount(User $user): void
    {
        try {
            // Not guarded beyond this: any other failure must fail the job, so it retries and then
            // lands in failed_jobs with the real cause instead of leaving the user half deleted
            $this->userRepository->deleteUser(userId: $user->id);
        } catch (UserNotFoundException) {
            // Already removed by an earlier attempt: a retried job must not fail on finished work
        }
    }
}
