<?php

namespace Database\Seeders;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Support\RiddleHelper;
use App\Support\RinjoraData;
use App\Support\RinjoraTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiddleSeeder extends Seeder
{
    /**
     * Full 216-item SOKWE set from docs/rinjora.html, stored in the
     * "Ibisokozo" category. Answers may carry `/` alternatives (preserved by
     * RiddleHelper::normalize); difficulty is derived from the prototype's
     * difficulte() score. The source data carries no hints, so hint/hint2 stay
     * null.
     */
    public function run(): void
    {
        $now = now();

        $category = RiddleCategory::query()
            ->where('name', 'Ibisokozo')
            ->orWhere('slug', Str::slug('Ibisokozo'))
            ->first();

        if (! $category) {
            $category = RiddleCategory::create([
                'name' => 'Ibisokozo',
                'slug' => Str::slug('Ibisokozo'),
                'description' => 'Ibisokozo bigezweho vya SOKWE ku rurimi rw\'ikirundi.',
            ]);
        }

        foreach (RinjoraData::sokwe() as $item) {
            Riddle::updateOrCreate(
                ['question' => $item['q']],
                [
                    'category_id' => $category->id,
                    'answer' => RiddleHelper::normalize($item['a']),
                    'difficulty' => RinjoraTier::tier(RinjoraTier::difficulte($item), 52, 66),
                    'riddle_type' => 'riddle',
                    'hint' => null,
                    'hint2' => null,
                    'source' => 'Sokwe y\'ikirundi',
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}