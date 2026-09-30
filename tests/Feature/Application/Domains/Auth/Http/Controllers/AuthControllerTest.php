<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Enums\TokenAbility;
use App\Domains\Auth\Events\UserDeleted;
use App\Domains\Auth\Events\UserRegistered;
use App\Domains\Auth\Notifications\CustomResetPasswordNotification;
use App\Domains\Auth\Notifications\CustomVerificationNotification;
use App\Models\User as UserModel;
use Illuminate\Auth\Events\Verified;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

class AuthControllerTest extends FeatureTestCase
{
    private const string ALLOWED_ORIGIN = 'http://localhost:4200';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.allowed_redirect_origins', [self::ALLOWED_ORIGIN]);
    }

    // -------------------------------------------------------------------------
    // register
    // -------------------------------------------------------------------------
    #[Test]
    public function register_works_correctly(): void
    {
        Event::fake([UserRegistered::class]);
        Config::set('app.timezone', 'UTC');

        $this
            ->postJson(route('auth.register'), $this->registrationPayload(['preferred_language' => 'it']))
            ->assertSuccessful()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_expires_at', 'has_verified_email'])
            ->assertJsonPath('has_verified_email', false);

        $this->assertDatabaseHas(UserModel::class, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@email.com',
            'preferred_language' => 'it',
            'timezone' => 'UTC',
        ]);

        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->email === 'johndoe@email.com');
    }

    #[Test]
    public function register_returns_the_token_expiry_as_a_unix_timestamp(): void
    {
        Event::fake([UserRegistered::class]);
        $this->freezeTime();
        Config::set('sanctum.access_token_expiration', 30);

        $this
            ->postJson(route('auth.register'), $this->registrationPayload())
            ->assertSuccessful()
            ->assertJsonPath('token_expires_at', now()->addMinutes(30)->getTimestamp());
    }

    #[Test]
    public function register_throws_exception_if_user_already_exist(): void
    {
        Event::fake([UserRegistered::class]);

        $this->createUser(['first_name' => 'John', 'email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.register'), $this->registrationPayload(['first_name' => 'Impostor']))
            ->assertUnprocessable();

        $this->assertDatabaseMissing(UserModel::class, ['first_name' => 'Impostor']);
        Event::assertNotDispatched(UserRegistered::class);
    }

    #[Test]
    public function register_refuses_an_email_that_differs_only_in_case(): void
    {
        Event::fake([UserRegistered::class]);

        $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.register'), $this->registrationPayload(['email' => 'JohnDoe@Email.com']))
            ->assertUnprocessable();

        $this->assertSame(1, UserModel::query()->count());
        Event::assertNotDispatched(UserRegistered::class);
    }

    #[Test]
    public function register_rejects_an_email_longer_than_its_column(): void
    {
        Event::fake([UserRegistered::class]);

        $this
            ->postJson(route('auth.register'), $this->registrationPayload(['email' => str_repeat('a', 250) . '@email.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function register_with_redirect_url_passes_it_to_the_verification_email(): void
    {
        Notification::fake();

        $this
            ->postJson(route('auth.register'), $this->registrationPayload([
                'after_verification_redirect_url' => self::ALLOWED_ORIGIN . '/welcome',
            ]))
            ->assertSuccessful();

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomVerificationNotification::class,
            fn (CustomVerificationNotification $notification): bool => $notification->afterVerificationRedirectUrl === self::ALLOWED_ORIGIN . '/welcome',
        );
    }

    #[Test]
    public function register_rejects_a_redirect_url_off_the_allowed_origins(): void
    {
        Event::fake([UserRegistered::class]);

        $this
            ->postJson(route('auth.register'), $this->registrationPayload([
                'after_verification_redirect_url' => 'https://evil.example/welcome',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('after_verification_redirect_url');

        $this->assertDatabaseMissing(UserModel::class, ['email' => 'johndoe@email.com']);
    }

    // -------------------------------------------------------------------------
    // login
    // -------------------------------------------------------------------------
    #[Test]
    public function login_works_correctly(): void
    {
        $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'Password124#'])
            ->assertSuccessful()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_expires_at', 'has_verified_email'])
            ->assertJsonPath('has_verified_email', true);
    }

    #[Test]
    public function login_answers_a_wrong_password_and_an_unknown_email_identically(): void
    {
        $this->createUser(['email' => 'johndoe@email.com']);

        $wrongPassword = $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'WrongPassword123#'])
            ->assertUnauthorized();

        $unknownEmail = $this
            ->postJson(route('auth.login'), ['email' => 'nobody@email.com', 'password' => 'WrongPassword123#'])
            ->assertUnauthorized();

        $this->assertSame('Invalid credentials.', $wrongPassword->json('message'));
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    #[Test]
    public function login_matches_the_email_whatever_its_case(): void
    {
        $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.login'), ['email' => 'JohnDoe@Email.COM', 'password' => 'Password124#'])
            ->assertSuccessful();
    }

    #[Test]
    public function login_refuses_an_account_pending_deletion_like_a_wrong_password(): void
    {
        $this->createUser(['email' => 'johndoe@email.com', 'deletion_requested_at' => now()]);

        $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'Password124#'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    // -------------------------------------------------------------------------
    // Token abilities
    // -------------------------------------------------------------------------
    /**
     * @return array<string, array{string, string}>
     */
    public static function accessTokenRoutes(): array
    {
        return [
            'logout' => ['postJson', 'auth.logout'],
            'send_verification_email' => ['postJson', 'auth.send_verification_email'],
            'delete_account' => ['deleteJson', 'auth.delete_account'],
        ];
    }

    #[Test]
    #[DataProvider('accessTokenRoutes')]
    public function a_refresh_token_is_refused_by_every_route_that_takes_an_access_token(string $method, string $routeName): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();

        $userModel = $this->createUser();
        $refreshToken = $userModel->createToken('refresh_token', [TokenAbility::IssueAccessToken->value]);

        $this
            ->withToken($refreshToken->plainTextToken)
            ->{$method}(route($routeName), ['after_verification_redirect_url' => self::ALLOWED_ORIGIN . '/welcome'])
            ->assertForbidden();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $refreshToken->accessToken->id]);
        Event::assertNotDispatched(UserDeleted::class);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------
    #[Test]
    public function logout_works_correctly(): void
    {
        $userModel = $this->createUser();

        $this
            ->withToken($this->accessToken($userModel))
            ->postJson(route('auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $userModel->id]);
    }

    // -------------------------------------------------------------------------
    // forgotPassword
    // -------------------------------------------------------------------------
    #[Test]
    public function forgotPassword_works_correctly(): void
    {
        Notification::fake();

        $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.forgot_password'), [
                'email' => 'johndoe@email.com',
                'password_reset_page_url' => self::ALLOWED_ORIGIN . '/reset-password',
            ])
            ->assertNoContent();

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomResetPasswordNotification::class,
            fn (CustomResetPasswordNotification $notification): bool => $notification->email === 'johndoe@email.com'
                && $notification->passwordResetPageUrl === self::ALLOWED_ORIGIN . '/reset-password',
        );
    }

    #[Test]
    public function forgotPassword_answers_an_unknown_email_the_same_way(): void
    {
        Notification::fake();

        $this
            ->postJson(route('auth.forgot_password'), [
                'email' => 'nobody@email.com',
                'password_reset_page_url' => self::ALLOWED_ORIGIN . '/reset-password',
            ])
            ->assertNoContent();

        Notification::assertNothingSent();
    }

    #[Test]
    public function forgotPassword_sends_nothing_to_an_account_pending_deletion(): void
    {
        Notification::fake();

        $this->createUser(['email' => 'johndoe@email.com', 'deletion_requested_at' => now()]);

        $this
            ->postJson(route('auth.forgot_password'), [
                'email' => 'johndoe@email.com',
                'password_reset_page_url' => self::ALLOWED_ORIGIN . '/reset-password',
            ])
            ->assertNoContent();

        Notification::assertNothingSent();
    }

    #[Test]
    public function forgotPassword_rejects_a_reset_page_off_the_allowed_origins(): void
    {
        Notification::fake();

        $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.forgot_password'), [
                'email' => 'johndoe@email.com',
                'password_reset_page_url' => 'https://evil.example/reset-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password_reset_page_url');

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // resetPassword
    // -------------------------------------------------------------------------
    #[Test]
    public function resetPassword_works_correctly(): void
    {
        $userModel = $this->createUser(['email' => 'johndoe@email.com']);
        $token = Password::createToken($userModel);

        $this
            ->postJson(route('auth.reset_password'), [
                'email' => 'johndoe@email.com',
                'password' => 'NewPassword123#',
                'password_confirmation' => 'NewPassword123#',
                'token' => $token,
            ])
            ->assertNoContent();

        $this->assertTrue(Hash::check('NewPassword123#', $userModel->fresh()->password));
    }

    #[Test]
    public function resetPassword_throws_exception_for_password_reset_failed(): void
    {
        $userModel = $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->postJson(route('auth.reset_password'), [
                'email' => 'johndoe@email.com',
                'password' => 'NewPassword123#',
                'password_confirmation' => 'NewPassword123#',
                'token' => 'invalid-token',
            ])
            ->assertUnprocessable();

        $this->assertTrue(Hash::check('Password124#', $userModel->fresh()->password));
    }

    // -------------------------------------------------------------------------
    // sendEmailVerificationNotification
    // -------------------------------------------------------------------------
    #[Test]
    public function sendEmailVerificationNotification_works_correctly(): void
    {
        Notification::fake();

        $userModel = $this->createUser(['email_verified_at' => null]);

        $this
            ->withToken($this->accessToken($userModel))
            ->postJson(route('auth.send_verification_email'), [
                'after_verification_redirect_url' => self::ALLOWED_ORIGIN . '/welcome',
            ])
            ->assertNoContent();

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomVerificationNotification::class,
            fn (CustomVerificationNotification $notification): bool => $notification->user->id?->value === $userModel->id,
        );
    }

    #[Test]
    public function sendEmailVerificationNotification_rejects_a_redirect_off_the_allowed_origins(): void
    {
        Notification::fake();

        $userModel = $this->createUser(['email_verified_at' => null]);

        $this
            ->withToken($this->accessToken($userModel))
            ->postJson(route('auth.send_verification_email'), [
                'after_verification_redirect_url' => 'https://evil.example/welcome',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('after_verification_redirect_url');

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // verifyAccount
    // -------------------------------------------------------------------------
    #[Test]
    public function verifyAccount_works_correctly(): void
    {
        Event::fake([Verified::class]);

        $userModel = $this->createUser(['email_verified_at' => null]);

        $this
            ->getJson($this->verificationUrl($userModel->id, sha1($userModel->email)))
            ->assertNoContent();

        $this->assertNotNull($userModel->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->getAuthIdentifier() === $userModel->id);
    }

    #[Test]
    public function verifyAccount_with_redirect_url_redirects_there(): void
    {
        Event::fake([Verified::class]);

        $userModel = $this->createUser(['email_verified_at' => null]);

        $this
            ->getJson($this->verificationUrl($userModel->id, sha1($userModel->email), self::ALLOWED_ORIGIN . '/dashboard'))
            ->assertRedirect(self::ALLOWED_ORIGIN . '/dashboard');

        $this->assertNotNull($userModel->fresh()->email_verified_at);
    }

    #[Test]
    public function verifyAccount_throws_exception_for_not_found_user(): void
    {
        $this
            ->getJson($this->verificationUrl(999999, 'hash'))
            ->assertNotFound();
    }

    #[Test]
    public function verifyAccount_throws_exception_for_invalid_verification_link(): void
    {
        Event::fake([Verified::class]);

        $userModel = $this->createUser(['email_verified_at' => null]);

        $this
            ->getJson($this->verificationUrl($userModel->id, 'invalid-hash'))
            ->assertUnprocessable();

        $this->assertNull($userModel->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    #[Test]
    public function verifyAccount_rejects_a_link_whose_signature_was_tampered_with(): void
    {
        $userModel = $this->createUser(['email_verified_at' => null]);

        $url = $this->verificationUrl($userModel->id, sha1($userModel->email)) . '&redirect_url=https://evil.example';

        $this->getJson($url)->assertForbidden();

        $this->assertNull($userModel->fresh()->email_verified_at);
    }

    // -------------------------------------------------------------------------
    // deleteAccount
    // -------------------------------------------------------------------------
    #[Test]
    public function deleteAccount_works_correctly(): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();

        $userModel = $this->createUser();

        $this
            ->withToken($this->accessToken($userModel))
            ->deleteJson(route('auth.delete_account'))
            ->assertNoContent();

        // The row is removed by the queued DeleteUserAccountOnUserDeletedListener, so the request
        // itself only revokes access and dispatches the event that starts that pipeline
        $this->assertDatabaseHas('users', ['id' => $userModel->id]);
        $this->assertNotNull($userModel->refresh()->deletion_requested_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $userModel->id]);
        Event::assertDispatched(UserDeleted::class, fn (UserDeleted $event): bool => $event->user->id?->value === $userModel->id);
    }

    #[Test]
    public function deleteAccount_locks_the_account_out_before_its_row_is_removed(): void
    {
        Event::fake([UserDeleted::class]);
        Notification::fake();

        $userModel = $this->createUser(['email' => 'johndoe@email.com']);

        $this
            ->withToken($this->accessToken($userModel))
            ->deleteJson(route('auth.delete_account'))
            ->assertNoContent();

        // A fresh, unauthenticated client: the guard would otherwise still remember the user
        $this->app['auth']->forgetGuards();

        $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'Password124#'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    // -------------------------------------------------------------------------
    // refreshToken
    // -------------------------------------------------------------------------
    #[Test]
    public function refreshToken_works_correctly(): void
    {
        $userModel = $this->createUser();
        $refreshToken = $userModel->createToken('refresh_token', [TokenAbility::IssueAccessToken->value], now()->addWeek());

        $this
            ->withToken($refreshToken->plainTextToken)
            ->postJson(route('auth.refresh_token'))
            ->assertSuccessful()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_expires_at', 'has_verified_email']);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $userModel->id, 'name' => 'access_token']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $userModel->id, 'name' => 'refresh_token']);
    }

    #[Test]
    public function refreshToken_keeps_a_remembered_session_remembered(): void
    {
        $this->freezeTime();
        $this->createUser(['email' => 'johndoe@email.com']);

        $refreshToken = $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'Password124#', 'remember_me' => true])
            ->json('refresh_token');

        $this
            ->withToken($refreshToken)
            ->postJson(route('auth.refresh_token'))
            ->assertSuccessful()
            ->assertJsonPath('token_expires_at', now()->addDay()->getTimestamp());

        $this->assertSame(
            now()->addYear()->getTimestamp(),
            UserModel::query()->firstOrFail()->tokens()->where('name', 'remembered_refresh_token')->firstOrFail()->expires_at?->getTimestamp(),
        );
    }

    #[Test]
    public function refreshToken_cannot_stretch_a_standard_session(): void
    {
        $this->freezeTime();
        Config::set('sanctum.access_token_expiration', 60);
        Config::set('sanctum.refresh_token_expiration', 7 * 24 * 60);
        $this->createUser(['email' => 'johndoe@email.com']);

        $refreshToken = $this
            ->postJson(route('auth.login'), ['email' => 'johndoe@email.com', 'password' => 'Password124#'])
            ->json('refresh_token');

        $this
            ->withToken($refreshToken)
            ->postJson(route('auth.refresh_token'), ['remember_me' => true])
            ->assertSuccessful()
            ->assertJsonPath('token_expires_at', now()->addHour()->getTimestamp());

        $this->assertSame(
            now()->addWeek()->getTimestamp(),
            UserModel::query()->firstOrFail()->tokens()->where('name', 'refresh_token')->firstOrFail()->expires_at?->getTimestamp(),
        );
    }

    #[Test]
    public function refreshToken_refuses_an_account_pending_deletion(): void
    {
        $userModel = $this->createUser(['deletion_requested_at' => now()]);
        $refreshToken = $userModel->createToken('refresh_token', [TokenAbility::IssueAccessToken->value], now()->addWeek());

        $this
            ->withToken($refreshToken->plainTextToken)
            ->postJson(route('auth.refresh_token'))
            ->assertUnauthorized();
    }

    #[Test]
    public function refreshToken_deletes_old_tokens(): void
    {
        $userModel = $this->createUser();
        $oldAccessToken = $userModel->createToken('access_token', [TokenAbility::AccessApi->value], now()->addHour());
        $oldRefreshToken = $userModel->createToken('refresh_token', [TokenAbility::IssueAccessToken->value], now()->addWeek());

        $this
            ->withToken($oldRefreshToken->plainTextToken)
            ->postJson(route('auth.refresh_token'))
            ->assertSuccessful();

        $this->assertSame(2, $userModel->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldAccessToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldRefreshToken->accessToken->id]);
    }

    #[Test]
    public function refreshToken_requires_proper_token_ability(): void
    {
        $userModel = $this->createUser();

        $this
            ->withToken($this->accessToken($userModel))
            ->postJson(route('auth.refresh_token'))
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): UserModel
    {
        return UserModel::factory()->create([
            'password' => Hash::make('Password124#'),
            ...$attributes,
        ]);
    }

    private function accessToken(UserModel $userModel): string
    {
        return $userModel->createToken('access_token', [TokenAbility::AccessApi->value], now()->addHour())->plainTextToken;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@email.com',
            'password' => 'Password124#',
            'password_confirmation' => 'Password124#',
            'terms' => true,
            'preferred_language' => 'en',
            ...$overrides,
        ];
    }

    private function verificationUrl(int $userId, string $hash, ?string $redirectUrl = null): string
    {
        return URL::temporarySignedRoute(
            name: 'verification.verify',
            expiration: now()->addMinutes(60),
            parameters: array_filter(['userId' => $userId, 'hash' => $hash, 'redirect_url' => $redirectUrl]),
        );
    }
}
