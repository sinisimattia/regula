<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Models;

use App\Domains\Auth\Notifications\CustomVerificationNotification;
use App\Models\User as UserModel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\FeatureTestCase;

/**
 * Tests for the admin panel access rule on User, and the Super Admin bypass:
 * - canAccessPanel_admits_a_super_admin_even_when_unverified
 * - canAccessPanel_admits_a_verified_user_with_any_role
 * - canAccessPanel_refuses_an_unverified_user_with_a_role
 * - canAccessPanel_refuses_a_verified_user_without_a_role
 * - super_admin_passes_every_gate
 * - toEntity_carries_the_id_display_name_email_and_verification
 * - toUserAccount_carries_the_credentials_and_preferences
 * - sendEmailVerificationNotification_sends_through_the_account_service
 * - sendEmailVerificationNotification_sends_nothing_to_a_verified_user
 */
class UserTest extends FeatureTestCase
{
    #[Test]
    public function canAccessPanel_admits_a_super_admin_even_when_unverified(): void
    {
        $user = UserModel::factory()->unverified()->create();
        $user->assignRole($this->role(UserModel::SUPER_ADMIN_ROLE));

        $this->assertTrue($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    #[Test]
    public function canAccessPanel_admits_a_verified_user_with_any_role(): void
    {
        $user = UserModel::factory()->create();
        $user->assignRole($this->role('support'));

        $this->assertTrue($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    #[Test]
    public function canAccessPanel_refuses_an_unverified_user_with_a_role(): void
    {
        $user = UserModel::factory()->unverified()->create();
        $user->assignRole($this->role('support'));

        $this->assertFalse($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    #[Test]
    public function canAccessPanel_refuses_a_verified_user_without_a_role(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getDefaultPanel()));
    }

    #[Test]
    public function super_admin_passes_every_gate(): void
    {
        $user = UserModel::factory()->create();
        $user->assignRole($this->role(UserModel::SUPER_ADMIN_ROLE));

        $this->assertTrue(Gate::forUser($user)->allows('an-ability-nobody-defined'));
        $this->assertTrue(Gate::forUser($user)->allows('delete', UserModel::factory()->create()));
    }

    #[Test]
    public function toEntity_carries_the_id_display_name_email_and_verification(): void
    {
        $user = UserModel::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'email_verified_at' => '2026-01-01 10:00:00',
        ]);

        $entity = $user->toEntity();

        $this->assertSame($user->id, $entity->id?->value);
        $this->assertSame('Jane Smith', $entity->displayName);
        $this->assertSame('jane@example.com', $entity->email);
        $this->assertSame('2026-01-01 10:00:00', $entity->emailVerifiedAt?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function toUserAccount_carries_the_credentials_and_preferences(): void
    {
        $user = UserModel::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'preferred_language' => 'it',
            'timezone' => 'Europe/Rome',
        ]);

        $account = $user->toUserAccount();

        $this->assertSame($user->id, $account->user->id?->value);
        $this->assertSame('Jane', $account->firstName);
        $this->assertSame('Smith', $account->lastName);
        $this->assertSame($user->password, $account->hashedPassword);
        $this->assertSame('it', $account->preferredLanguage);
        $this->assertSame('Europe/Rome', $account->timezone);
    }

    #[Test]
    public function sendEmailVerificationNotification_sends_through_the_account_service(): void
    {
        Notification::fake();

        $user = UserModel::factory()->unverified()->create(['email' => 'jane@example.com']);

        $user->sendEmailVerificationNotification();

        Notification::assertSentOnDemand(
            CustomVerificationNotification::class,
            fn (CustomVerificationNotification $notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'jane@example.com',
        );
    }

    #[Test]
    public function sendEmailVerificationNotification_sends_nothing_to_a_verified_user(): void
    {
        Notification::fake();

        $user = UserModel::factory()->create();

        $user->sendEmailVerificationNotification();

        Notification::assertNothingSent();
    }

    private function role(string $name): Role
    {
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }
}
