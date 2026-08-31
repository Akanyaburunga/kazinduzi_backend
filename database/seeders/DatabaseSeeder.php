<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            WordsFromJsonSeeder::class,
            RiddleCategorySeeder::class,
            RiddleSeeder::class,
            ProverbSeeder::class,
            JokeSeeder::class,
            AchievementSeeder::class,
            TestDataSeeder::class,
        ]);
    }
}
