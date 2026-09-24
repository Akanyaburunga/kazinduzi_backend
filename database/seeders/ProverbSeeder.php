<?php

namespace Database\Seeders;

use App\Models\Proverb;
use App\Models\RiddleCategory;
use App\Support\RiddleHelper;
use App\Support\RinjoraData;
use App\Support\RinjoraTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProverbSeeder extends Seeder
{
    /**
     * Full HERAHEZA proverb set from docs/rinjora-data.json — complete the
     * ending. Sourced from the HERAHEZA array via RinjoraData; stored one row
     * per (question, answer) pair so duplicate setups with two valid endings
     * each persist as distinct challenges (162 rows total).
     *
     * Designed to be ADD-ONLY: existing rows (by question + answer) are never
     * touched, so admin edits, suspensions and timestamps survive re-seeding.
     */
    public function run(): void
    {
        $category = RiddleCategory::query()
            ->where('name', 'Imigani')
            ->orWhere('slug', Str::slug('Imigani'))
            ->first();

        if (! $category) {
            $category = RiddleCategory::create([
                'name' => 'Imigani',
                'slug' => Str::slug('Imigani'),
                'description' => 'Imigani n\'imigani y\'ikirundi.',
            ]);
        }

        foreach (RinjoraData::heraheza() as $item) {
            Proverb::firstOrCreate(
                [
                    'question' => $item['q'],
                    'answer' => RiddleHelper::normalize($item['a']),
                ],
                [
                    'category_id' => $category->id,
                    'difficulty' => RinjoraTier::tier(RinjoraTier::difficulte($item), 37, 50),
                    'source' => 'Heraheza y\'ikirundi',
                    'is_suspended' => false,
                ]
            );
        }
    }
}
