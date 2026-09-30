<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Auth\Entities\User as UserEntity;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Entities\UserId;
use App\Domains\Auth\Services\AccountServiceInterface;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string $password
 * @property string $preferred_language
 * @property string $timezone
 * @property CarbonInterface|null $email_verified_at
 * @property CarbonInterface|null $last_logged_at
 * @property CarbonInterface|null $deletion_requested_at
 * @property string|null $remember_token
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    public const SUPER_ADMIN_ROLE = 'Super Admin';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_logged_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $guarded = [
        'id',
    ];

    public function toEntity(): UserEntity
    {
        return new UserEntity(
            id: new UserId($this->getKey()),
            displayName: $this->getDisplayName(),
            email: $this->email,
            emailVerifiedAt: $this->email_verified_at?->toDateTimeImmutable(),
        );
    }

    public function toUserAccount(): UserAccount
    {
        return new UserAccount(
            user: $this->toEntity(),
            firstName: $this->first_name,
            lastName: $this->last_name,
            hashedPassword: $this->password,
            preferredLanguage: $this->preferred_language,
            timezone: $this->timezone,
            deletionRequestedAt: $this->deletion_requested_at?->toDateTimeImmutable(),
        );
    }

    public function getDisplayName(): ?string
    {
        return !empty($this->name) ?
            $this->name :
            str($this->email)
                ->before('@')
                ->toString();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()->exists() && $this->hasVerifiedEmail();
    }

    public function sendEmailVerificationNotification(): void
    {
        app(AccountServiceInterface::class)->sendEmailVerificationNotification(userId: new UserId($this->getKey()));
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
        event(new Verified($this));
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}
