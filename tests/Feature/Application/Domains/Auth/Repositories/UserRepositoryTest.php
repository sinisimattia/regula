<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Domains\Auth\Repositories;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Exceptions\PasswordResetThrottledException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Domains\Auth\Repositories\UserRepository;
use App\Models\User as UserModel;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\FeatureTestCase;

/**
 * Tests for UserRepository:
 * - storeUserAccount_inserts_an_account_without_an_id_and_returns_it_as_stored
 * - storeUserAccount_updates_the_account_its_id_names
 * - storeUserAccount_throws_user_already_exists_for_a_taken_email_and_leaves_the_connection_usable
 * - getUserById_throws_for_an_unknown_id
 * - getUserAccountByEmail_throws_for_an_unknown_email
 * - storeUserAccount_stores_the_email_in_lower_case
 * - getUserAccountByEmail_matches_whatever_the_case
 * - storeUserAccount_refuses_an_email_that_differs_only_in_case
 * - markDeletionRequested_shows_on_the_account
 * - hasRememberedRefreshToken_tells_a_remembered_pair_from_a_standard_one
 * - deleteUser_throws_for_an_unknown_id
 * - detachRolesAndPermissionsFromUser_removes_both
 * - storePasswordResetToken_throws_when_a_token_was_issued_moments_ago
 */
class UserRepositoryTest extends FeatureTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(UserRepository::class);
    }

    #[Test]
    public function storeUserAccount_inserts_an_account_without_an_id_and_returns_it_as_stored(): void
    {
        $account = new UserAccount(
            user: new User(displayName: 'Jane Smith', email: 'jane@example.com'),
            firstName: 'Jane',
            lastName: 'Smith',
            hashedPassword: bcrypt('Password124#'),
            preferredLanguage: 'it',
            timezone: 'Europe/Rome',
        );

        $storedAccount = $this->repository->storeUserAccount($account);

        $this->assertNotNull($storedAccount->user->id);
        $this->assertSame('Jane Smith', $storedAccount->user->displayName);
        $this->assertSame('jane@example.com', $storedAccount->user->email);
        $this->assertSame('Jane', $storedAccount->firstName);
        $this->assertSame('Smith', $storedAccount->lastName);
        $this->assertSame($account->hashedPassword, $storedAccount->hashedPassword);
        $this->assertSame('it', $storedAccount->preferredLanguage);
        $this->assertSame('Europe/Rome', $storedAccount->timezone);
        $this->assertFalse($storedAccount->user->hasVerifiedEmail());
    }

    #[Test]
    public function storeUserAccount_updates_the_account_its_id_names(): void
    {
        $userModel = UserModel::factory()->create(['first_name' => 'Jane', 'preferred_language' => 'en']);

        $storedAccount = $this->repository->storeUserAccount(new UserAccount(
            user: new User(id: new UserId($userModel->id)),
            preferredLanguage: 'de',
        ));

        $this->assertSame($userModel->id, $storedAccount->user->id?->value);
        $this->assertSame('de', $storedAccount->preferredLanguage);
        $this->assertSame('Jane', $storedAccount->firstName);
        $this->assertSame(1, UserModel::query()->count());
    }

    #[Test]
    public function storeUserAccount_throws_user_already_exists_for_a_taken_email_and_leaves_the_connection_usable(): void
    {
        UserModel::factory()->create(['email' => 'jane@example.com']);

        try {
            $this->repository->storeUserAccount(new UserAccount(
                user: new User(email: 'jane@example.com'),
                hashedPassword: bcrypt('Password124#'),
                preferredLanguage: 'en',
                timezone: 'UTC',
            ));

            $this->fail('A second account with the same email was stored.');
        } catch (UserAlreadyExistsException) {
            // Expected: the savepoint rolled back only the failed insert
        }

        $this->assertSame(1, UserModel::query()->where('email', 'jane@example.com')->count());
    }

    #[Test]
    public function getUserById_throws_for_an_unknown_id(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->getUserById(new UserId(999999));
    }

    #[Test]
    public function getUserAccountByEmail_throws_for_an_unknown_email(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->getUserAccountByEmail('nobody@example.com');
    }

    #[Test]
    public function storeUserAccount_stores_the_email_in_lower_case(): void
    {
        $storedAccount = $this->repository->storeUserAccount(new UserAccount(
            user: new User(email: 'Jane.Smith@Example.COM'),
            hashedPassword: bcrypt('Password124#'),
            preferredLanguage: 'en',
        ));

        $this->assertSame('jane.smith@example.com', $storedAccount->user->email);
    }

    #[Test]
    public function getUserAccountByEmail_matches_whatever_the_case(): void
    {
        $userModel = UserModel::factory()->create(['email' => 'jane@example.com']);

        $account = $this->repository->getUserAccountByEmail('JANE@Example.com');

        $this->assertSame($userModel->id, $account->user->id?->value);
    }

    #[Test]
    public function storeUserAccount_refuses_an_email_that_differs_only_in_case(): void
    {
        UserModel::factory()->create(['email' => 'jane@example.com']);

        $this->expectException(UserAlreadyExistsException::class);

        $this->repository->storeUserAccount(new UserAccount(
            user: new User(email: 'Jane@Example.com'),
            hashedPassword: bcrypt('Password124#'),
            preferredLanguage: 'en',
        ));
    }

    #[Test]
    public function markDeletionRequested_shows_on_the_account(): void
    {
        $userModel = UserModel::factory()->create(['deletion_requested_at' => null]);
        $userId = new UserId($userModel->id);

        $this->assertFalse($this->repository->getUserAccountById($userId)->isPendingDeletion());

        $this->repository->markDeletionRequested($userId);

        $this->assertTrue($this->repository->getUserAccountById($userId)->isPendingDeletion());
    }

    #[Test]
    public function hasRememberedRefreshToken_tells_a_remembered_pair_from_a_standard_one(): void
    {
        $userModel = UserModel::factory()->create();
        $userId = new UserId($userModel->id);
        $expiresAt = now()->addWeek()->toDateTimeImmutable();

        $this->repository->issueTokenPair($userId, $expiresAt, $expiresAt, remembered: false);
        $this->assertFalse($this->repository->hasRememberedRefreshToken($userId));

        $this->repository->issueTokenPair($userId, $expiresAt, $expiresAt, remembered: true);
        $this->assertTrue($this->repository->hasRememberedRefreshToken($userId));
    }

    #[Test]
    public function deleteUser_throws_for_an_unknown_id(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->deleteUser(new UserId(999999));
    }

    #[Test]
    public function detachRolesAndPermissionsFromUser_removes_both(): void
    {
        $userModel = UserModel::factory()->create();
        $userModel->assignRole(Role::query()->create(['name' => 'support', 'guard_name' => 'web']));
        $userModel->givePermissionTo(Permission::query()->create(['name' => 'view users', 'guard_name' => 'web']));

        $this->repository->detachRolesAndPermissionsFromUser(new UserId($userModel->id));

        $this->assertFalse($userModel->fresh()->roles()->exists());
        $this->assertFalse($userModel->fresh()->permissions()->exists());
    }

    #[Test]
    public function storePasswordResetToken_throws_when_a_token_was_issued_moments_ago(): void
    {
        $userModel = UserModel::factory()->create();
        $this->repository->storePasswordResetToken(new UserId($userModel->id));

        $this->expectException(PasswordResetThrottledException::class);

        $this->repository->storePasswordResetToken(new UserId($userModel->id));
    }
}
