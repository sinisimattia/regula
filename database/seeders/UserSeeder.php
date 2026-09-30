<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::factory(10)->create();

        /** @var User $admin */
        $admin = User::query()->firstOrCreate(
            ['email' => config('app.admin_email')],
            [
                'name' => 'Admin',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email_verified_at' => now(),
                'preferred_language' => 'en',
                'timezone' => 'UTC',
                'password' => 'password',
            ],
        );

        $admin->assignRole(User::SUPER_ADMIN_ROLE);
    }
}
