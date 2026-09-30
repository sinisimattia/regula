<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Domains\Auth\Services;

use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Enums\TokenAbility;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\PasswordResetFailedException;
use App\Domains\Auth\Notifications\CustomResetPasswordNotification;
use App\Domains\Auth\Services\AuthService;
use App\Models\User as UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Tests for AuthService:
 * - loginWithCredentials_issues_a_token_pair_and_stamps_last_login
 * - loginWithCredentials_throws_invalid_credentials_for_a_wrong_password
 * - loginWithCredentials_throws_invalid_credentials_for_an_unknown_email
 * - loginWithCredentials_checks_a_password_even_for_an_unknown_email
 * - loginWithCredentials_throws_invalid_credentials_for_an_account_pending_deletion
 * - issueTokens_uses_the_configured_lifetimes_and_abilities
 * - issueTokens_with_remember_me_extends_both_lifetimes
 * - issueTokens_revokes_every_earlier_token
 * - issueTokens_refuses_an_account_pending_deletion
 * - refreshTokens_keeps_a_remembered_session_remembered
 * - refreshTokens_keeps_a_standard_session_standard
 * - logout_revokes_all_user_tokens
 * - sendPasswordResetLink_emails_a_link_to_the_page_asked_for
 * - sendPasswordResetLink_succeeds_silently_for_an_unknown_email
 * - sendPasswordResetLink_sends_nothing_to_an_account_pending_deletion
 * - sendPasswordResetLink_sends_nothing_for_a_repeat_within_the_throttle
 * - resetUserPassword_updates_password_and_consumes_the_token
 * - resetUserPassword_revokes_existing_tokens
 * - resetUserPassword_throws_exception_for_invalid_token
 * - resetUserPassword_throws_exception_for_unknown_email
 */
class AuthServiceTest extends FeatureTestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AuthService::class);
    }

    // -------------------------------------------------------------------------
    // loginWithCredentials
    // -------------------------------------------------------------------------
    #[Test]
    public function loginWithCredentials_issues_a_token_pair_and_stamps_last_login(): void
    {
        $this->freezeTime();

        $userModel = UserModel::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password124#'),
            'last_logged_at' => null,
        ]);

        $tokenPair = $this->service->loginWithCredentials(email: 'jane@example.com', password: 'Password124#', rememberMe: false);

        $this->assertSame($userModel->id, $tokenPair->user->id?->value);
        $this->assertNotSame('', $tokenPair->accessToken);
        $this->assertNotSame('', $tokenPair->refreshToken);
        $this->assertSame(now()->getTimestamp(), $userModel->fresh()->last_logged_at?->getTimestamp());
    }

    #[Test]
    public function loginWithCredentials_throws_invalid_credentials_for_a_wrong_password(): void
    {
        UserModel::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('Password124#')]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->loginWithCredentials(email: 'jane@example.com', password: 'WrongPassword124#', rememberMe: false);
    }

    #[Test]
    public function loginWithCredentials_throws_invalid_credentials_for_an_unknown_email(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->service->loginWithCredentials(email: 'nobody@example.com', password: 'Password124#', rememberMe: false);
    }

    #[Test]
    public function loginWithCredentials_checks_a_password_even_for_an_unknown_email(): void
    {
        $checkedHashes = [];
        Hash::shouldReceive('check')->andReturnUsing(function (string $password, string $hash) use (&$checkedHashes): bool {
            $checkedHashes[] = $hash;

            return false;
        });

        try {
            $this->service->loginWithCredentials(email: 'nobody@example.com', password: 'Password124#', rememberMe: false);
            $this->fail('An unknown email must be refused.');
        } catch (InvalidCredentialsException) {
            // Expected: what matters is that a hash was checked first
        }

        $this->assertCount(1, $checkedHashes);
        $this->assertStringStartsWith('$2y$', $checkedHashes[0]);
    }

    #[Test]
    public function loginWithCredentials_throws_invalid_credentials_for_an_account_pending_deletion(): void
    {
        UserModel::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password124#'),
            'deletion_requested_at' => now(),
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->loginWithCredentials(email: 'jane@example.com', password: 'Password124#', rememberMe: false);
    }

    // -------------------------------------------------------------------------
    // issueTokens
    // -------------------------------------------------------------------------
    #[Test]
    public function issueTokens_uses_the_configured_lifetimes_and_abilities(): void
    {
        $this->freezeTime();
        Config::set('sanctum.access_token_expiration', 30);
        Config::set('sanctum.refresh_token_expiration', 600);

        $userModel = UserModel::factory()->create();

        $tokenPair = $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: false);

        $this->assertSame(now()->addMinutes(30)->getTimestamp(), $tokenPair->accessTokenExpiresAt->getTimestamp());

        $accessToken = PersonalAccessToken::findToken($tokenPair->accessToken);
        $refreshToken = PersonalAccessToken::findToken($tokenPair->refreshToken);

        $this->assertSame([TokenAbility::AccessApi->value], $accessToken?->abilities);
        $this->assertSame([TokenAbility::IssueAccessToken->value], $refreshToken?->abilities);
        $this->assertSame(now()->addMinutes(30)->getTimestamp(), $accessToken?->expires_at?->getTimestamp());
        $this->assertSame(now()->addMinutes(600)->getTimestamp(), $refreshToken?->expires_at?->getTimestamp());
    }

    #[Test]
    public function issueTokens_with_remember_me_extends_both_lifetimes(): void
    {
        $this->freezeTime();
        Config::set('sanctum.access_token_expiration', 30);
        Config::set('sanctum.refresh_token_expiration', 600);

        $userModel = UserModel::factory()->create();

        $tokenPair = $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: true);

        $this->assertSame(now()->addDay()->getTimestamp(), $tokenPair->accessTokenExpiresAt->getTimestamp());
        $this->assertSame(
            now()->addYear()->getTimestamp(),
            PersonalAccessToken::findToken($tokenPair->refreshToken)?->expires_at?->getTimestamp(),
        );
    }

    #[Test]
    public function issueTokens_revokes_every_earlier_token(): void
    {
        $userModel = UserModel::factory()->create();
        $earlierToken = $userModel->createToken('access_token', [TokenAbility::AccessApi->value]);

        $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: false);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $earlierToken->accessToken->id]);
        $this->assertSame(2, $userModel->tokens()->count());
    }

    #[Test]
    public function issueTokens_refuses_an_account_pending_deletion(): void
    {
        $userModel = UserModel::factory()->create(['deletion_requested_at' => now()]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: false);
    }

    // -------------------------------------------------------------------------
    // refreshTokens
    // -------------------------------------------------------------------------
    #[Test]
    public function refreshTokens_keeps_a_remembered_session_remembered(): void
    {
        $this->freezeTime();
        $userModel = UserModel::factory()->create();
        $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: true);

        $tokenPair = $this->service->refreshTokens(userId: new UserId($userModel->id));

        $this->assertSame(now()->addDay()->getTimestamp(), $tokenPair->accessTokenExpiresAt->getTimestamp());
        $this->assertSame(
            now()->addYear()->getTimestamp(),
            PersonalAccessToken::findToken($tokenPair->refreshToken)?->expires_at?->getTimestamp(),
        );
    }

    #[Test]
    public function refreshTokens_keeps_a_standard_session_standard(): void
    {
        $this->freezeTime();
        Config::set('sanctum.access_token_expiration', 30);
        Config::set('sanctum.refresh_token_expiration', 600);
        $userModel = UserModel::factory()->create();
        $this->service->issueTokens(userId: new UserId($userModel->id), rememberMe: false);

        $tokenPair = $this->service->refreshTokens(userId: new UserId($userModel->id));

        $this->assertSame(now()->addMinutes(30)->getTimestamp(), $tokenPair->accessTokenExpiresAt->getTimestamp());
        $this->assertSame(
            now()->addMinutes(600)->getTimestamp(),
            PersonalAccessToken::findToken($tokenPair->refreshToken)?->expires_at?->getTimestamp(),
        );
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------
    #[Test]
    public function logout_revokes_all_user_tokens(): void
    {
        $userModel = UserModel::factory()->create();
        $userModel->createToken('access_token');
        $userModel->createToken('refresh_token');

        $this->service->logout(userId: new UserId($userModel->id));

        $this->assertSame(0, $userModel->tokens()->count());
    }

    // -------------------------------------------------------------------------
    // sendPasswordResetLink
    // -------------------------------------------------------------------------
    #[Test]
    public function sendPasswordResetLink_emails_a_link_to_the_page_asked_for(): void
    {
        Notification::fake();

        UserModel::factory()->create(['email' => 'jane@example.com']);

        $this->service->sendPasswordResetLink(email: 'jane@example.com', passwordResetPageUrl: 'http://localhost:4200/reset');

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            CustomResetPasswordNotification::class,
            fn (CustomResetPasswordNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'jane@example.com'
                && $notification->email === 'jane@example.com'
                && $notification->passwordResetPageUrl === 'http://localhost:4200/reset'
                && $notification->token !== '',
        );
    }

    #[Test]
    public function sendPasswordResetLink_succeeds_silently_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->service->sendPasswordResetLink(email: 'nobody@example.com', passwordResetPageUrl: 'http://localhost:4200/reset');

        Notification::assertNothingSent();
    }

    #[Test]
    public function sendPasswordResetLink_sends_nothing_to_an_account_pending_deletion(): void
    {
        Notification::fake();
        UserModel::factory()->create(['email' => 'jane@example.com', 'deletion_requested_at' => now()]);

        $this->service->sendPasswordResetLink(email: 'jane@example.com', passwordResetPageUrl: 'http://localhost:4200/reset');

        Notification::assertNothingSent();
    }

    #[Test]
    public function sendPasswordResetLink_sends_nothing_for_a_repeat_within_the_throttle(): void
    {
        Notification::fake();

        UserModel::factory()->create(['email' => 'jane@example.com']);

        $this->service->sendPasswordResetLink(email: 'jane@example.com', passwordResetPageUrl: 'http://localhost:4200/reset');
        $this->service->sendPasswordResetLink(email: 'jane@example.com', passwordResetPageUrl: 'http://localhost:4200/reset');

        Notification::assertSentTimes(CustomResetPasswordNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // resetUserPassword
    // -------------------------------------------------------------------------
    #[Test]
    public function resetUserPassword_updates_password_and_consumes_the_token(): void
    {
        $userModel = UserModel::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('OldPassword123#'),
        ]);
        $token = Password::createToken($userModel);

        $this->service->resetUserPassword(email: 'jane@example.com', password: 'NewPassword123#', token: $token);

        $this->assertTrue(Hash::check('NewPassword123#', $userModel->fresh()->password));
        $this->assertFalse(Password::tokenExists($userModel, $token));
    }

    #[Test]
    public function resetUserPassword_revokes_existing_tokens(): void
    {
        $userModel = UserModel::factory()->create(['email' => 'jane@example.com']);
        $userModel->createToken('access_token');
        $userModel->createToken('refresh_token');
        $token = Password::createToken($userModel);

        $this->service->resetUserPassword(email: 'jane@example.com', password: 'NewPassword123#', token: $token);

        $this->assertSame(0, $userModel->tokens()->count());
    }

    #[Test]
    public function resetUserPassword_throws_exception_for_invalid_token(): void
    {
        UserModel::factory()->create(['email' => 'jane@example.com']);

        $this->expectException(PasswordResetFailedException::class);

        $this->service->resetUserPassword(email: 'jane@example.com', password: 'NewPassword123#', token: 'invalid-token');
    }

    #[Test]
    public function resetUserPassword_throws_exception_for_unknown_email(): void
    {
        $this->expectException(PasswordResetFailedException::class);

        $this->service->resetUserPassword(email: 'nobody@example.com', password: 'NewPassword123#', token: 'any-token');
    }
}
