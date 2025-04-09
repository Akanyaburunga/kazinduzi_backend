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
        User::firstOrCreate(
            ['email' => 'admin@kazinduzi.org'],
            [
                'name' => 'Admin',
                'password' => Hash::make(env('DEFAULT_ADMIN_PASSWORD', '12345678@@v')),
                'email_verified_at' => now(),
            ]
        );
    }
}
