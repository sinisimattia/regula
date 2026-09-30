<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * The models the admin policies guard, each checked for these four actions.
     */
    private const ADMIN_MODELS = ['User', 'Role', 'Permission'];

    private const ADMIN_ACTIONS = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        foreach (self::ADMIN_MODELS as $model) {
            foreach (self::ADMIN_ACTIONS as $action) {
                Permission::query()->firstOrCreate(['name' => "{$action} {$model}", 'guard_name' => 'web']);
            }
        }

        Role::query()->firstOrCreate(['name' => User::SUPER_ADMIN_ROLE, 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
