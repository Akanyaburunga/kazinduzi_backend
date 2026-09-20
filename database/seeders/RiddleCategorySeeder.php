<?php

namespace Database\Seeders;

use App\Models\RiddleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiddleCategorySeeder extends Seeder
{
    /**
     * The three rinjora-parity categories (one per game mode). Riddles,
     * proverbs and jokes are grouped under these, mirroring the prototype's
     * SOKWE / HERAHEZA / TUJAJURE collections only.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Ibisokozo', 'description' => 'Ibisokozo bigezweho vya SOKWE ku rurimi rw\'ikirundi.'],
            ['name' => 'Imigani', 'description' => 'Imigani n\'imigani y\'ikirundi.'],
            ['name' => 'Utujajuro', 'description' => 'Utujajuro n\'utunenge turi kuberuriwe.'],
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