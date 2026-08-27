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

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // Ensure the admin passes the reputation gate for the panel. reputation
        // is not mass-assignable, so set it directly.
        $threshold = (int) env('MODERATION_REPUTATION_THRESHOLD', 500);
        if ((int) $user->reputation < $threshold + 100) {
            $user->reputation = $threshold + 100;
            $user->save();
        }

        $this->command->info('Default admin created or already exists.');

    }
}
