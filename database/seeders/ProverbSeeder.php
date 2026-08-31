<?php

namespace Database\Seeders;

use App\Models\Proverb;
use App\Models\RiddleCategory;
use App\Support\RiddleHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProverbSeeder extends Seeder
{
    /**
     * Starter set of Kirundi proverbs (Heraheza) — complete the ending.
     * Sourced from the HERAHEZA array in docs/rinjora.html.
     */
    public function run(): void
    {
        $proverbs = [
            ['q' => 'Iyo wombye amazi mu kibindi ntakohera…', 'a' => 'inkoko', 'd' => 'medium'],
            ['q' => 'Amazi arashuha…', 'a' => 'ntiyibagira i bumbeho', 'd' => 'medium'],
            ['q' => 'Akamuntu kamara…', 'a' => 'iyagwe', 'd' => 'hard'],
            ['q' => 'Akanse…', 'a' => 'baraheba', 'd' => 'easy'],
            ['q' => 'Akazi k\'i bwami…', 'a' => 'kica uwicaye', 'd' => 'medium'],
            ['q' => 'Amarira y\'umugabo…', 'a' => 'atemba aja mu nda', 'd' => 'medium'],
            ['q' => 'Igiti kigororwa…', 'a' => 'kikiri gito', 'd' => 'easy'],
            ['q' => 'Ikinyoma kimara umusi…', 'a' => 'ntikimara umwaka', 'd' => 'easy'],
            ['q' => 'Imana ifasha…', 'a' => 'uwifashije', 'd' => 'easy'],
            ['q' => 'Ubuntu…', 'a' => 'burihabwa', 'd' => 'easy'],
            ['q' => 'Ukora ineza…', 'a' => 'ukayisanga imbere', 'd' => 'medium'],
            ['q' => 'Ukora inabi…', 'a' => 'ikagukurikira', 'd' => 'medium'],
            ['q' => 'Umubanyi niwe…', 'a' => 'muryango', 'd' => 'easy'],
            ['q' => 'Umutwe umwe…', 'a' => 'ntiwigira inama', 'd' => 'easy'],
            ['q' => 'Urya nk\'inka…', 'a' => 'ugapfa nk\'imbwa', 'd' => 'medium'],
            ['q' => 'Nta wanka kwonka nyina…', 'a' => 'ngo arwaye amahere', 'd' => 'hard'],
            ['q' => 'Imbwa yarishuse avyara…', 'a' => 'ibihumye', 'd' => 'medium'],
            ['q' => 'Ivya gusa…', 'a' => 'bitera ubwenge buke', 'd' => 'hard'],
        ];

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

        $now = now();

        foreach ($proverbs as $data) {
            Proverb::updateOrCreate(
                ['question' => $data['q']],
                [
                    'category_id' => $category->id,
                    'answer' => RiddleHelper::normalize($data['a']),
                    'difficulty' => $data['d'],
                    'source' => 'Imigani y\'ikirundi',
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
