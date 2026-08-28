<?php

namespace Database\Seeders;

use App\Models\RiddleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiddleCategorySeeder extends Seeder
{
    /**
     * Core Kazinduzi riddle categories.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Imigani', 'description' => 'Imigani y\'ikirundi ndetse n\'imigani y\'abantu.'],
            ['name' => 'Indorerezi', 'description' => 'Indorerezi z\'inyamaswa, ibimera n\'ibintu.'],
            ['name' => 'Ibikorwa', 'description' => 'Ibikorwa bya buri musi by\'umunyamakuru.'],
            ['name' => 'Inkuru', 'description' => 'Inkuru ngufi z\'utuntu n\'utundi.'],
            ['name' => 'Ibintu', 'description' => 'Ibintu biri ahantu? Ni iki?'],
            ['name' => 'Ubugenge', 'description' => 'Ubugenge n\'ibibazo byo gutekereza.'],
        ];

        foreach ($categories as $data) {
            RiddleCategory::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]
            );
        }
    }
}
