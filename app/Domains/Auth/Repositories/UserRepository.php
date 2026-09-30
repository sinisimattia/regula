<?php

declare(strict_types=1);

namespace App\Domains\Auth\Repositories;

use App\Domains\Auth\Entities\TokenPair;
use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Enums\TokenAbility;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Exceptions\PasswordResetThrottledException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Helpers\DatabaseErrorHelper;
use App\Models\User as UserModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Password;

class UserRepository implements UserRepositoryInterface
{
    private const REFRESH_TOKEN_NAME = 'refresh_token';

    private const REMEMBERED_REFRESH_TOKEN_NAME = 'remembered_refresh_token';

    public function storeUserAccount(UserAccount $account): UserAccount
    {
        $userModel = $account->user->id === null
            ? new UserModel()
            : $this->getUserModel($account->user->id);

        if ($account->user->email !== null) {
            $userModel->email = $this->normalisedEmail($account->user->email);
        }

        if ($account->user->displayName !== null) {
            $userModel->name = $account->user->displayName;
        }

        if ($account->firstName !== null) {
            $userModel->first_name = $account->firstName;
        }

        if ($account->lastName !== null) {
            $userModel->last_name = $account->lastName;
        }

        if ($account->hashedPassword !== null) {
            $userModel->password = $account->hashedPassword;
        }

        if ($account->preferredLanguage !== null) {
            $userModel->preferred_language = $account->preferredLanguage;
        }

        if ($account->timezone !== null) {
            $userModel->timezone = $account->timezone;
        }

        if ($account->deletionRequestedAt !== null) {
            $userModel->deletion_requested_at = $account->deletionRequestedAt;
        }

        try {
            // A savepoint of its own, so a duplicate rolls back only this insert and leaves any
            // enclosing transaction usable
            $userModel->getConnection()->transaction(fn (): bool => $userModel->save());
        } catch (QueryException $e) {
            if (DatabaseErrorHelper::isUniqueViolation($e)) {
                throw new UserAlreadyExistsException(previous: $e);
            }

            throw $e;
        }

        return $userModel->refresh()->toUserAccount();
    }

    public function getUserById(UserId $userId): User
    {
        return $this->getUserModel($userId)->toEntity();
    }

    public function getUserAccountById(UserId $userId): UserAccount
    {
        return $this->getUserModel($userId)->toUserAccount();
    }

    public function getUserAccountByEmail(string $email): UserAccount
    {
        try {
            /** @var UserModel $userModel */
            $userModel = UserModel::query()->where('email', $this->normalisedEmail($email))->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new UserNotFoundException(previous: $e);
        }

        return $userModel->toUserAccount();
    }

    public function deleteUser(UserId $userId): void
    {
        $this->getUserModel($userId)->delete();
    }

    public function markEmailAsVerified(UserId $userId): void
    {
        $this->getUserModel($userId)->markEmailAsVerified();
    }

    public function markDeletionRequested(UserId $userId): void
    {
        $this->getUserModel($userId)->forceFill(['deletion_requested_at' => now()])->save();
    }

    public function recordLogin(UserId $userId): void
    {
        $this->getUserModel($userId)->forceFill(['last_logged_at' => now()])->save();
    }

    public function issueTokenPair(
        UserId $userId,
        DateTimeImmutable $accessTokenExpiresAt,
        DateTimeImmutable $refreshTokenExpiresAt,
        bool $remembered,
    ): TokenPair {
        $userModel = $this->getUserModel($userId);

        $userModel->tokens()->delete();

        $accessToken = $userModel->createToken(
            name: 'access_token',
            abilities: [TokenAbility::AccessApi->value],
            expiresAt: $accessTokenExpiresAt,
        );

        $refreshToken = $userModel->createToken(
            name: $remembered ? self::REMEMBERED_REFRESH_TOKEN_NAME : self::REFRESH_TOKEN_NAME,
            abilities: [TokenAbility::IssueAccessToken->value],
            expiresAt: $refreshTokenExpiresAt,
        );

        return new TokenPair(
            user: $userModel->toEntity(),
            accessToken: $accessToken->plainTextToken,
            refreshToken: $refreshToken->plainTextToken,
            accessTokenExpiresAt: $accessTokenExpiresAt,
        );
    }

    public function hasRememberedRefreshToken(UserId $userId): bool
    {
        return $this->getUserModel($userId)->tokens()->where('name', self::REMEMBERED_REFRESH_TOKEN_NAME)->exists();
    }

    public function deleteTokens(UserId $userId): void
    {
        $this->getUserModel($userId)->tokens()->delete();
    }

    public function detachRolesAndPermissionsFromUser(UserId $userId): void
    {
        $userModel = $this->getUserModel($userId);

        $userModel->permissions()->detach();
        $userModel->roles()->detach();
    }

    public function storePasswordResetToken(UserId $userId): string
    {
        $userModel = $this->getUserModel($userId);
        $tokenRepository = Password::broker()->getRepository();

        if ($tokenRepository->recentlyCreatedToken($userModel)) {
            throw new PasswordResetThrottledException();
        }

        return $tokenRepository->create($userModel);
    }

    public function resetUserPassword(UserId $userId, string $password, string $token): void
    {
        $userModel = $this->getUserModel($userId);
        $tokenRepository = Password::broker()->getRepository();

        if (!$tokenRepository->exists($userModel, $token)) {
            throw new PasswordResetFailedException(status: Password::INVALID_TOKEN);
        }

        // The `hashed` cast hashes the plain password on save
        $userModel->forceFill(['password' => $password])->save();
        $userModel->tokens()->delete();
        $tokenRepository->delete($userModel);
    }

    private function normalisedEmail(string $email): string
    {
        return mb_strtolower($email);
    }

    /**
     * @throws UserNotFoundException
     */
    private function getUserModel(UserId $userId): UserModel
    {
        try {
            /** @var UserModel $userModel */
            $userModel = UserModel::query()->findOrFail($userId->value);
        } catch (ModelNotFoundException $e) {
            throw new UserNotFoundException(previous: $e);
        }

        return $userModel;
    }
}
