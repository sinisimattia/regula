<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Domains\Auth\Services;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Exceptions\InvalidVerificationLinkException;
use App\Domains\Auth\Exceptions\UserAlreadyExistsException;
use App\Domains\Auth\Exceptions\UserNotFoundException;
use App\Domains\Auth\Notifications\CustomDeleteAccountNotification;
use App\Domains\Auth\Notifications\CustomVerificationNotification;
use App\Domains\Auth\Services\AccountService;
use App\Models\User as UserModel;
use Illuminate\Auth\Events\Verified;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\FeatureTestCase;

/**
 * Tests for AccountService:
 * - registerUser_stores_the_account_with_a_hashed_password_and_dispatches_the_event
 * - registerUser_signs_the_new_user_in
 * - registerUser_sends_the_verification_email
 * - registerUser_throws_exception_for_duplicate_email
 * - sendEmailVerificationNotification_sends_to_an_unverified_user
 * - sendEmailVerificationNotification_sends_nothing_to_a_verified_user
 * - verifyEmail_marks_email_as_verified
 * - verifyEmail_is_a_no_op_for_an_already_verified_user
 * - verifyEmail_throws_exception_for_invalid_hash
 * - verifyEmail_throws_exception_when_user_not_found
 * - requestAccountDeletion_revokes_access_and_dispatches_event
 * - requestAccountDeletion_marks_the_account_pending_deletion
 * - requestAccountDeletion_detaches_roles_and_notifies_the_user
 * - deleteUserAccount_deletes_the_user_row
 * - deleteUserAccount_does_not_fail_when_the_user_is_already_gone
 */
class AccountServiceTest extends FeatureTestCase
{
    private AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AccountService::class);
    }

    // -------------------------------------------------------------------------
    // registerUser
    // -------------------------------------------------------------------------
    #[Test]
    public function registerUser_stores_the_account_with_a_hashed_password_and_dispatches_the_event(): void
    {
        Event::fake([UserRegistered::class]);

        $this->service->registerUser(
            account: $this->registrationAccount('jane@example.com'),
            password: 'Password124#',
            afterVerificationRedirectUrl: 'http://localhost:4200/welcome',
        );

        /** @var UserModel $userModel */
        $userModel = UserModel::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame('Jane', $userModel->first_name);
        $this->assertSame('Smith', $userModel->last_name);
        $this->assertNotSame('Password124#', $userModel->password);
        $this->assertTrue(Hash::check('Password124#', $userModel->password));

        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->id?->value === $userModel->id
            && $event->afterVerificationRedirectUrl === 'http://localhost:4200/welcome');
    }

    #[Test]
    public function registerUser_signs_the_new_user_in(): void
    {
        Event::fake([UserRegistered::class]);

        $tokenPair = $this->service->registerUser(
            account: $this->registrationAccount('jane@example.com'),
            password: 'Password124#',
        );

        /** @var UserModel $userModel */
        $userModel = UserModel::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame($userModel->id, $tokenPair->user->id?->value);
        $this->assertFalse($tokenPair->user->hasVerifiedEmail());
        $this->assertSame(2, $userModel->tokens()->count());
    }

    #[Test]
    public function registerUser_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->service->registerUser(
            account: $this->registrationAccount('jane@example.com'),
            password: 'Password124#',
        );

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomVerificationNotification::class,
            fn (CustomVerificationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'jane@example.com',
        );
    }

    #[Test]
    public function registerUser_throws_exception_for_duplicate_email(): void
    {
        Event::fake([UserRegistered::class]);

        UserModel::factory()->create(['email' => 'duplicate@example.com']);

        $this->expectException(UserAlreadyExistsException::class);

        try {
            $this->service->registerUser(
                account: $this->registrationAccount('duplicate@example.com'),
                password: 'Password124#',
            );
        } finally {
            Event::assertNotDispatched(UserRegistered::class);
        }
    }

    // -------------------------------------------------------------------------
    // sendEmailVerificationNotification
    // -------------------------------------------------------------------------
    #[Test]
    public function sendEmailVerificationNotification_sends_to_an_unverified_user(): void
    {
        Notification::fake();

        $userModel = UserModel::factory()->unverified()->create(['email' => 'jane@example.com']);

        $this->service->sendEmailVerificationNotification(
            userId: new UserId($userModel->id),
            afterVerificationRedirectUrl: 'http://localhost:4200/welcome',
        );

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomVerificationNotification::class,
            fn (CustomVerificationNotification $notification): bool => $notification->user->id?->value === $userModel->id
                && $notification->afterVerificationRedirectUrl === 'http://localhost:4200/welcome',
        );
    }

    #[Test]
    public function sendEmailVerificationNotification_sends_nothing_to_a_verified_user(): void
    {
        Notification::fake();

        $userModel = UserModel::factory()->create();

        $this->service->sendEmailVerificationNotification(userId: new UserId($userModel->id));

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // verifyEmail
    // -------------------------------------------------------------------------
    #[Test]
    public function verifyEmail_marks_email_as_verified(): void
    {
        Event::fake([Verified::class]);

        $userModel = UserModel::factory()->unverified()->create();

        $this->service->verifyEmail(userId: new UserId($userModel->id), hash: sha1($userModel->email));

        $this->assertNotNull($userModel->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->getAuthIdentifier() === $userModel->id);
    }

    #[Test]
    public function verifyEmail_is_a_no_op_for_an_already_verified_user(): void
    {
        Event::fake([Verified::class]);

        $userModel = UserModel::factory()->create(['email_verified_at' => now()->subDay()]);
        $verifiedAt = $userModel->email_verified_at?->getTimestamp();

        $this->service->verifyEmail(userId: new UserId($userModel->id), hash: sha1($userModel->email));

        $this->assertSame($verifiedAt, $userModel->fresh()->email_verified_at?->getTimestamp());
        Event::assertNotDispatched(Verified::class);
    }

    #[Test]
    public function verifyEmail_throws_exception_for_invalid_hash(): void
    {
        $userModel = UserModel::factory()->unverified()->create();

        $this->expectException(InvalidVerificationLinkException::class);

        $this->service->verifyEmail(userId: new UserId($userModel->id), hash: 'invalid-hash');
    }

    #[Test]
    public function verifyEmail_throws_exception_when_user_not_found(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->verifyEmail(userId: new UserId(999999), hash: 'any-hash');
    }

    // -------------------------------------------------------------------------
    // requestAccountDeletion
    // -------------------------------------------------------------------------
    #[Test]
    public function requestAccountDeletion_revokes_access_and_dispatches_event(): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();

        $userModel = UserModel::factory()->create();
        $userModel->createToken('access_token');

        $this->service->requestAccountDeletion(userId: new UserId($userModel->id));

        // The row itself is removed later, by the queued listener
        $this->assertDatabaseHas('users', ['id' => $userModel->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $userModel->id]);
        Event::assertDispatched(UserDeleted::class, fn (UserDeleted $event): bool => $event->user->id?->value === $userModel->id);
    }

    #[Test]
    public function requestAccountDeletion_marks_the_account_pending_deletion(): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();
        $this->freezeTime();

        $userModel = UserModel::factory()->create(['deletion_requested_at' => null]);

        $this->service->requestAccountDeletion(userId: new UserId($userModel->id));

        $this->assertSame(now()->getTimestamp(), $userModel->fresh()->deletion_requested_at?->getTimestamp());
    }

    #[Test]
    public function requestAccountDeletion_detaches_roles_and_notifies_the_user(): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();

        $userModel = UserModel::factory()->create(['email' => 'jane@example.com']);
        $userModel->assignRole(Role::query()->create(['name' => 'support', 'guard_name' => 'web']));

        $this->service->requestAccountDeletion(userId: new UserId($userModel->id));

        $this->assertFalse($userModel->fresh()->roles()->exists());
        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomDeleteAccountNotification::class,
            fn (CustomDeleteAccountNotification $notification): bool => $notification->email === 'jane@example.com',
        );
    }

    // -------------------------------------------------------------------------
    // deleteUserAccount
    // -------------------------------------------------------------------------
    #[Test]
    public function deleteUserAccount_deletes_the_user_row(): void
    {
        $userModel = UserModel::factory()->create();

        $this->service->deleteUserAccount($userModel->toEntity());

        $this->assertDatabaseMissing('users', ['id' => $userModel->id]);
    }

    #[Test]
    public function deleteUserAccount_does_not_fail_when_the_user_is_already_gone(): void
    {
        $userModel = UserModel::factory()->create();
        $user = $userModel->toEntity();
        $userModel->delete();

        // A retried job must treat finished work as done rather than fail on it
        $this->service->deleteUserAccount($user);

        $this->assertDatabaseMissing('users', ['id' => $userModel->id]);
    }

    private function registrationAccount(string $email): UserAccount
    {
        return new UserAccount(
            user: new User(displayName: 'Jane Smith', email: $email),
            firstName: 'Jane',
            lastName: 'Smith',
            preferredLanguage: 'en',
            timezone: 'UTC',
        );
    }
}
