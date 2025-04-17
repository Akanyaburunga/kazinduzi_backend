<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin user if it doesn't already exist
        $email = env('DEFAULT_ADMIN_EMAIL');
        $name = env('DEFAULT_ADMIN_NAME');
        $password = env('DEFAULT_ADMIN_PASSWORD');

        if (!$email || !$name || !$password) {
            $this->command->error('Missing default admin credentials in .env file.');
            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Default admin created or already exists.');

    }
}
