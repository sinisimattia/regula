<?php

declare(strict_types=1);

namespace App\Domains\Auth\Providers;

use App\Domains\Auth\Repositories\UserRepository;
use App\Domains\Auth\Repositories\UserRepositoryInterface;
use App\Domains\Auth\Services\AccountService;
use App\Domains\Auth\Services\AccountServiceInterface;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\AuthServiceInterface;
use App\Models\User;
use App\Policies\Admin\PermissionPolicy;
use App\Policies\Admin\RolePolicy;
use App\Policies\Admin\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Super Admin bypasses all permission checks
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }

    public function register(): void
    {
        $this->app->bind(AccountServiceInterface::class, AccountService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
