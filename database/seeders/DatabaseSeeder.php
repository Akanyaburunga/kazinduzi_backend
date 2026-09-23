<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only the rinjora.json dataset is seeded (SOKWE / HERAHEZA / TUJAJURE
     * plus their three categories) together with the default admin user. No
     * demo users, word dictionary or badge catalogue data.
     *
     * Every seeder is add-only: it inserts rows that are missing and never
     * mutates existing rows, so re-seeding production is safe.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            RiddleCategorySeeder::class,
            RiddleSeeder::class,
            ProverbSeeder::class,
            JokeSeeder::class,
        ]);
    }
}
