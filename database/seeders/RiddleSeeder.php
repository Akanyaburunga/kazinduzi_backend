<?php

namespace Database\Seeders;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Support\RiddleHelper;
use Illuminate\Database\Seeder;

class RiddleSeeder extends Seeder
{
    /**
     * Starter set of real Kirundi riddles.
     */
    public function run(): void
    {
        $riddles = [
            [
                'category' => 'Imigani',
                'question' => 'Umugabo wanjye aza mu rugo ntakunda',
                'answer' => 'umugore',
                'difficulty' => 'easy',
                'hint' => 'Ntibimuka, ahubwo ni ikintu cose abana naco.',
                'hint2' => 'Uzigama mu rugo, ni we ushinzwe imyonga yose.',
                'source' => 'Imigani y\'ikirundi',
            ],
            [
                'category' => 'Imigani',
                'question' => 'Iyo wombye amazi mu kibindi ntakohera',
                'answer' => 'inkoko',
                'difficulty' => 'medium',
                'hint' => 'Irafise ibaba, ariko ntiraguruka kure.',
                'hint2' => 'Yorora abana bayo mu masaka.',
                'source' => 'Imigani y\'ikirundi',
            ],
            [
                'category' => 'Indorerezi',
                'question' => 'Ubuki ntibutemba, ariko bugira ubuyuki',
                'answer' => 'inzuki',
                'difficulty' => 'hard',
                'hint' => 'Bikorera mu mabara y\'umuhondo n\'umukara.',
                'hint2' => 'Birakanye, kandi bitsinda abantu.',
                'source' => 'Kazinduzi indorerezi STB',
            ],
        ];

        $now = now();

        foreach ($riddles as $data) {
            $category = RiddleCategory::query()
                ->where('name', $data['category'])
                ->orWhere('slug', \Illuminate\Support\Str::slug($data['category']))
                ->first();

            if (! $category) {
                continue;
            }

            Riddle::updateOrCreate(
                ['question' => $data['question']],
                [
                    'category_id' => $category->id,
                    'answer' => RiddleHelper::normalize($data['answer']),
                    'difficulty' => $data['difficulty'],
                    'hint' => $data['hint'],
                    'hint2' => $data['hint2'],
                    'source' => $data['source'],
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
