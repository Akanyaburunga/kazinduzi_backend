<?php

namespace Database\Seeders;

use App\Support\Achievements;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /**
     * Seed/resync the default badge catalogue.
     */
    public function run(): void
    {
        Achievements::syncCatalogue();
    }
}
