<?php

namespace Database\Seeders;

use App\Models\Joke;
use App\Models\RiddleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JokeSeeder extends Seeder
{
    /**
     * Starter set of Kirundi jokes (Tujajure) — pick the punchline.
     * Sourced from the TUJAJURE array in docs/rinjora.html. Each joke's
     * distractors are drawn from the other jokes' punchlines (mirroring the
     * prototype's option generation).
     */
    public function run(): void
    {
        $jokes = [
            ['Agaca gacakiye agahori gati:', 'Mwana wa mama undiye twari bamwe.'],
            ['Agaca gacakiye agahori gati:', 'Nagira ngo akaguruka ntikoriye akandi.'],
            ['Agaca gacakiye agahori gati, kano kati:', 'Urandya neza ndahanda.'],
            ['Agahume gahwaniye n’akandi mu mwonga ngo:', 'Rinda tubane waragowe ndagorwa.'],
            ['Agakara kagiye iwa sebukwe ngo agataramure kano gahitanywe gaca mu mbavu ati:', 'Ni wewe ga mupfaso? Na ko ngo ego agakara gasa n’akandi.'],
            ['Agakecuru kasanze akandi mu kuzimu kati:', 'Nyereka ibigega wimbuye. Na ko kati: Noneho ngaho mama uraje kuvyiyimburira nawe.'],
            ['Agakecuru kashikuye igikoba kigata ku kingi kati:', 'Nakudya ntitwarwana.'],
            ['Agakecuru karose gapfa gati:', 'Maze simvyanka.'],
            ['Akagomba kaneye mu cibo kati:', 'Urantuma mugabo wa mama ishikanwa ry’umworo ni iryo.'],
            ['Akagomba kenzwe na se kati:', 'N’uburi mu mwonga nibushoke.'],
            ['Urunyegeri rwamenekanye n’intango ruti:', 'Abenga ni benge jeho ndenguye.'],
            ['Babajije gikona bati: «Mbega gikona ko ugenda wasamye?» Giti:', 'Ntawumenya iyo indya zituruka.'],
            ['Ikirogorye cagiye gusaba amazi kw’ijuru, inkuba iti: «Twa!» Giti:', 'Iyo mbura amazi ngakiza igufa.'],
            ['Babariye Samandari bati: «Urabona ga Samandari ukarya inkenekene zimena amaso?» Ati:', 'Ubusa bwo?'],
            ['Ikirogorye canyereye ku gishishwa, kiragwa, giti:', 'Haborewe jewe, hari akantu abaruganye!'],
            ['Imbwa yahuye n’umusaza, abura ico ayiha, iti:', 'Haguhura n’umusaza wohura n’umwana, abura ico aguha akaguherekeza canke akakwirukana.'],
        ];

        $category = RiddleCategory::query()
            ->where('name', 'Utujajuro')
            ->orWhere('slug', Str::slug('Utujajuro'))
            ->first();

        if (! $category) {
            $category = RiddleCategory::create([
                'name' => 'Utujajuro',
                'slug' => Str::slug('Utujajuro'),
                'description' => 'Utujajuro n’utunenge turi kuberuriwe.',
            ]);
        }

        $allPunchlines = array_values(array_unique(array_column($jokes, 1)));
        $now = now();

        foreach ($jokes as [$setup, $punchline]) {
            // Three distractors drawn from the other jokes' punchlines.
            $others = array_values(array_diff($allPunchlines, [$punchline]));
            sort($others);
            $distractors = array_slice($others, 0, 3);

            Joke::updateOrCreate(
                ['setup' => $setup, 'punchline' => $punchline],
                [
                    'category_id' => $category->id,
                    'distractors' => $distractors,
                    'source' => 'Utujajuro tw’ikirundi',
                    'is_suspended' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}