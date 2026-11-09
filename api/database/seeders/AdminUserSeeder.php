<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The first admin, from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD. With no
 * password set, a random one is generated and printed once.
 *
 * No factories or fake(): this runs on Forge, where dev dependencies aren't installed.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(config()->string('vaultnine.admin.email'));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->command->info("Admin {$email} already exists; left unchanged.");

            return;
        }

        $password = (string) config('vaultnine.admin.password');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(20);
        }

        $admin = new User([
            'name' => config()->string('vaultnine.admin.name'),
            'email' => $email,
            'password' => $password,
        ]);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now(), 'age_confirmed_at' => now()])->save();

        $this->command->info("Admin created: {$email}");
        if ($generated) {
            $this->command->warn("Generated password (shown once): {$password}");
        }
    }
}
