<?php

namespace Database\Seeders;

use App\Models\Joke;
use App\Models\RiddleCategory;
use App\Support\RinjoraData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JokeSeeder extends Seeder
{
    /**
     * Full 16-item TUJAJURE set from docs/rinjora.html — pick the punchline.
     * Each joke's distractors are drawn from the other jokes' punchlines
     * (mirroring the prototype's option generation).
     */
    public function run(): void
    {
        $jokes = array_map(
            fn (array $item) => ['setup' => $item['t'], 'punchline' => $item['p']],
            RinjoraData::tujajure()
        );

        $category = RiddleCategory::query()
            ->where('name', 'Utujajuro')
            ->orWhere('slug', Str::slug('Utujajuro'))
            ->first();

        if (! $category) {
            $category = RiddleCategory::create([
                'name' => 'Utujajuro',
                'slug' => Str::slug('Utujajuro'),
                'description' => 'Utujajuro n\'utunenge turi kuberuriwe.',
            ]);
        }

        $allPunchlines = array_values(array_unique(array_column($jokes, 'punchline')));
        $now = now();

        foreach ($jokes as $joke) {
            $setup = $joke['setup'];
            $punchline = $joke['punchline'];

            // Three distractors drawn from the other jokes' punchlines.
            $others = array_values(array_diff($allPunchlines, [$punchline]));
            sort($others);
            $distractors = array_slice($others, 0, 3);

            Joke::updateOrCreate(
                ['setup' => $setup, 'punchline' => $punchline],
                [
                    'category_id' => $category->id,
                    'distractors' => $distractors,
                    'source' => 'Utujajuro tw\'ikirundi',
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}