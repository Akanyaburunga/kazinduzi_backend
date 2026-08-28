<?php

namespace Database\Seeders;

use App\Models\Riddle;
use App\Models\RiddleCategory;
use App\Models\Tag;
use App\Support\RiddleHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiddleSeeder extends Seeder
{
    /**
     * Starter set of real Kirundi riddles spanning all categories, types and
     * difficulties, plus the tags that label them.
     */
    public function run(): void
    {
        $riddles = [
            // ====== Imigani ======
            [
                'category' => 'Imigani',
                'question' => 'Umugabo wanjye aza mu rugo ntakunda',
                'answer' => 'umugore',
                'difficulty' => 'easy',
                'type' => 'riddle',
                'hint' => 'Ntibimuka, ahubwo ni ikintu cose abana naco.',
                'hint2' => 'Uzigama mu rugo, ni we ushinzwe imyonga yose.',
                'source' => 'Imigani y\'ikirundi',
                'tags' => ['umuryango', 'abagore'],
            ],
            [
                'category' => 'Imigani',
                'question' => 'Iyo wombye amazi mu kibindi ntakohera',
                'answer' => 'inkoko',
                'difficulty' => 'medium',
                'type' => 'riddle',
                'hint' => 'Irafise ibaba, ariko ntiraguruka kure.',
                'hint2' => 'Yorora abana bayo mu masaka.',
                'source' => 'Imigani y\'ikirundi',
                'tags' => ['inyamaswa', 'urugo'],
            ],
            [
                'category' => 'Imigani',
                'question' => 'Nyina w\'umwana yitwa umwana, none uwo mwana yitwa iki?',
                'answer' => 'umwana',
                'difficulty' => 'easy',
                'type' => 'brain_teaser',
                'hint' => 'Tekereza kuri ijambo "umwana" ubwaryo.',
                'hint2' => 'Uhoraho ari umwana koko.',
                'source' => 'Imigani y\'ikirundi',
                'tags' => ['abana', 'gutekereza'],
            ],
            [
                'category' => 'Imigani',
                'question' => 'Inzu yanjye ntago ifise umuryango, ariko ntiwinjira imbere',
                'answer' => 'inkende',
                'difficulty' => 'hard',
                'type' => 'what_am_i',
                'hint' => 'Ibana mu biti vy\'imigano.',
                'hint2' => 'Ni nyampeke y\'umukara.',
                'source' => 'Imigani y\'ikirundi',
                'tags' => ['inyamaswa', 'amashamba'],
            ],
            // ====== Indorerezi ======
            [
                'category' => 'Indorerezi',
                'question' => 'Ubuki ntibutemba, ariko bugira ubuyuki',
                'answer' => 'inzuki',
                'difficulty' => 'hard',
                'type' => 'what_am_i',
                'hint' => 'Bikorera mu mabara y\'umuhondo n\'umukara.',
                'hint2' => 'Birakanye, kandi bitsinda abantu.',
                'source' => 'Kazinduzi indorerezi STB',
                'tags' => ['inyamaswa', 'umwuka'],
            ],
            [
                'category' => 'Indorerezi',
                'question' => 'Ndugutse nkurasa, ariko ntagira amaguru',
                'answer' => 'inzoka',
                'difficulty' => 'medium',
                'type' => 'what_am_i',
                'hint' => 'Ifata mu butore bw\'imbere.',
                'hint2' => 'Abantu benshi bayitinya.',
                'source' => 'Kazinduzi indorerezi',
                'tags' => ['inyamaswa', 'inkengere'],
            ],
            [
                'category' => 'Indorerezi',
                'question' => 'Iziramuka kare, iririmba mbere y\'izuba',
                'answer' => 'inkokokazi',
                'difficulty' => 'easy',
                'type' => 'who_am_i',
                'hint' => 'Yira inyoni iterura abantu mu gitondo.',
                'hint2' => 'Urukundo rwayo ni rwo rwiza mu rugo.',
                'source' => 'Kazinduzi indorerezi',
                'tags' => ['inyoni', 'izuba'],
            ],
            [
                'category' => 'Indorerezi',
                'question' => 'Mfise amabara atatu: umutuku, umuhondo n\'umutsinda',
                'answer' => 'urwa',
                'difficulty' => 'hard',
                'type' => 'what_am_i',
                'hint' => 'Nkunda inkungu n\'ibimera.',
                'hint2' => 'Nobera mu birunga vy\'ibombwe.',
                'source' => 'Kazinduzi indorerezi',
                'tags' => ['inyamaswa', 'amabara'],
            ],
            // ====== Ibikorwa ======
            [
                'category' => 'Ibikorwa',
                'question' => 'Ndondora ku muduga wanjye none nsanga utarimwe ku kibanza',
                'answer' => 'umupark',
                'difficulty' => 'medium',
                'type' => 'what_is_it',
                'hint' => 'Nta kantu kanini, nta kanini.',
                'hint2' => 'Ahabantu bashyira imodoka zabo.',
                'source' => 'Kazinduzi ibikorwa',
                'tags' => ['imodoka', 'umujyi'],
            ],
            [
                'category' => 'Ibikorwa',
                'question' => 'Mbere yo gusohoka nuhagarikwa, ntabwo ari umurwanizi',
                'answer' => 'ikirahuri',
                'difficulty' => 'easy',
                'type' => 'what_is_it',
                'hint' => 'Kirinda umuryango wanyu.',
                'hint2' => 'Urakibona ku muryango w\'inzu.',
                'source' => 'Kazinduzi ibikorwa',
                'tags' => ['inzu', 'umuryango'],
            ],
            [
                'category' => 'Ibikorwa',
                'question' => 'Nta muntu umwe ubonka nk\'umuntu udakoresha iki gikoresho mu rugo',
                'answer' => 'amazi',
                'difficulty' => 'easy',
                'type' => 'what_is_it',
                'hint' => 'Ni ikintu tugira kuri buri munsi.',
                'hint2' => 'Kiba mu kiziba, mu mugezi n\'mu kiraro.',
                'source' => 'Kazinduzi ibikorwa',
                'tags' => ['amazi', 'ubuzima'],
            ],
            [
                'category' => 'Ibikorwa',
                'question' => 'Umukorezi wanjye ararima, ariko ntibone kurima indabyo',
                'answer' => 'amateka',
                'difficulty' => 'hard',
                'type' => 'brain_teaser',
                'hint' => 'Ararima mu bishingo.',
                'hint2' => 'Ararima ibijanye n\'ubutaka n\'imicanwa.',
                'source' => 'Kazinduzi ibikorwa',
                'tags' => ['ubutaka', 'imirima'],
            ],
            // ====== Inkuru ======
            [
                'category' => 'Inkuru',
                'question' => 'Umugabo ari mu rugo, mugore ari mu rugo, n\'umwana ari mu rugo; umuryango wacu usanzwe uri iki?',
                'answer' => 'umuryango',
                'difficulty' => 'medium',
                'type' => 'riddle',
                'hint' => 'Ni ishamwe ry\'abantu bose.',
                'hint2' => 'Baturanye kandi bakorana.',
                'source' => 'Inkuru z\'ikirundi',
                'tags' => ['umuryango'],
            ],
            [
                'category' => 'Inkuru',
                'question' => 'Inyoni y\'umukomere yiruka, ariko sinyishobora gufata',
                'answer' => 'amajwi',
                'difficulty' => 'hard',
                'type' => 'what_am_i',
                'hint' => 'Urumva n\'amatwi, ariko ntubona.',
                'hint2' => 'Uraro imbarutso, uririmba.',
                'source' => 'Inkuru z\'ikirundi',
                'tags' => ['amajwi', 'ururimi'],
            ],
            [
                'category' => 'Inkuru',
                'question' => 'Umuhungu wanjye ari we musaza w\'umupfumu wanjye; ni nde?',
                'answer' => 'niwe',
                'difficulty' => 'easy',
                'type' => 'brain_teaser',
                'hint' => 'Tekereza ku muryango wanyu.',
                'hint2' => 'Umuhungu ni uw\'abavyeyi bamwe.',
                'source' => 'Inkuru z\'ikirundi',
                'tags' => ['abana', 'umuryango'],
            ],
            [
                'category' => 'Inkuru',
                'question' => 'Ndavuga kandi ndatura, ariko ntago nsoma',
                'answer' => 'umuvyaro',
                'difficulty' => 'medium',
                'type' => 'what_am_i',
                'hint' => 'Urumva mu icumba cyose.',
                'hint2' => 'Urahandika n\'abantu.',
                'source' => 'Inkuru z\'ikirundi',
                'tags' => ['amajwi', 'ubutumwa'],
            ],
            // ====== Ibintu ======
            [
                'category' => 'Ibintu',
                'question' => 'Nta maguru mfise, ariko nkomeza kugenda, ntagira umutima ariko ndimo kubaho',
                'answer' => 'umugezi',
                'difficulty' => 'easy',
                'type' => 'what_is_it',
                'hint' => 'Utemba municu.',
                'hint2' => 'Urafise amazi.',
                'source' => 'Kazinduzi ibintu',
                'tags' => ['amazi', 'imigenzo'],
            ],
            [
                'category' => 'Ibintu',
                'question' => 'Ntaroboza nta n\'imvura, ariko ndabaho cuzuye',
                'answer' => 'umuto',
                'difficulty' => 'medium',
                'type' => 'what_am_i',
                'hint' => 'Ndona mu kanwa abana bose.',
                'hint2' => 'Ntangira mumize y\'amazi.',
                'source' => 'Kazinduzi ibintu',
                'tags' => ['kanwa', 'abana'],
            ],
            [
                'category' => 'Ibintu',
                'question' => 'Nikukira uturere, nuga ndi mukati, simenya indwara',
                'answer' => 'urugo',
                'difficulty' => 'hard',
                'type' => 'riddle',
                'hint' => 'Irima imbere y\'inzu.',
                'hint2' => 'Niyo nzu nkuru yo kugubaka.',
                'source' => 'Kazinduzi ibintu',
                'tags' => ['inzu', 'urugo'],
            ],
            [
                'category' => 'Ibintu',
                'question' => 'Nta mbaragaza nta n\'abona, ariko ndagira umuriro mwinshi',
                'answer' => 'umuriro',
                'difficulty' => 'hard',
                'type' => 'what_am_i',
                'hint' => 'Ugerwaho bagakorana ubutumwa.',
                'hint2' => 'Urakanya mu isi yose.',
                'source' => 'Kazinduzi ibintu',
                'tags' => ['umuriro', 'isi'],
            ],
            // ====== Ubugenge ======
            [
                'category' => 'Ubugenge',
                'question' => 'Biriko imiryango 12, buri muryango ufise amasaha 60',
                'answer' => 'ijoro',
                'difficulty' => 'hard',
                'type' => 'math',
                'hint' => 'Tekereza ku gusaba iminsi.',
                'hint2' => 'Amafisha ni 12, asaha ni 60.',
                'source' => 'Kazinduzi ubugenge',
                'tags' => ['igikorwa', 'imisi'],
            ],
            [
                'category' => 'Ubugenge',
                'question' => 'Abana 3 bafise ibeye 5.barimwe. Ni ingano y\'ibintu bibabanye?',
                'answer' => 'ibisokoro',
                'difficulty' => 'medium',
                'type' => 'math',
                'hint' => 'Bitemba kuri buri munsi.',
                'hint2' => 'Abana barakibona.',
                'source' => 'Kazinduzi ubugenge',
                'tags' => ['abana', 'imibare'],
            ],
            [
                'category' => 'Ubugenge',
                'question' => 'Nta ntinzi, nta ntinzi: ikintu cose muri ibi gisumba ikindi?',
                'answer' => 'ikintu',
                'difficulty' => 'hard',
                'type' => 'brain_teaser',
                'hint' => 'Tekereza kuri vyose.',
                'hint2' => 'Nta kintu ciza.',
                'source' => 'Kazinduzi ubugenge',
                'tags' => ['gutekereza'],
            ],
            [
                'category' => 'Ubugenge',
                'question' => 'Nanwe mu kanwa nk\'umuriro, ndaguwe nk\'umuhondo, numva nk\'umunyu',
                'answer' => 'uruyuki',
                'difficulty' => 'medium',
                'type' => 'what_am_i',
                'hint' => 'No mu margarine durabwi.',
                'hint2' => 'Ni ikinure cy\'amazi.',
                'source' => 'Kazinduzi ubugenge',
                'tags' => ['kanwa', 'guta'],
            ],
        ];

        $tags = array_values(array_unique(array_merge(
            [],
            ...array_map(fn ($r) => $r['tags'], $riddles)
        )));

        foreach ($tags as $name) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        $now = now();

        foreach ($riddles as $data) {
            $category = RiddleCategory::query()
                ->where('name', $data['category'])
                ->orWhere('slug', Str::slug($data['category']))
                ->first();

            if (! $category) {
                $category = RiddleCategory::create([
                    'name' => $data['category'],
                    'slug' => Str::slug($data['category']),
                    'description' => 'Ibice by\'imigani n\'indorerezi.',
                ]);
            }

            $tags = Tag::whereIn('slug', array_map(fn ($t) => Str::slug($t), $data['tags']))->get();

            $riddle = Riddle::updateOrCreate(
                ['question' => $data['question']],
                [
                    'category_id' => $category->id,
                    'answer' => RiddleHelper::normalize($data['answer']),
                    'difficulty' => $data['difficulty'],
                    'riddle_type' => $data['type'] ?? 'riddle',
                    'hint' => $data['hint'],
                    'hint2' => $data['hint2'],
                    'source' => $data['source'],
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $riddle->tags()->sync($tags->pluck('id'));
        }
    }
}
