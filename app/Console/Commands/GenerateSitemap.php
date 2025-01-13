<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use App\Models\Word;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate the sitemap for Kazinduzi app';

    public function handle()
    {
        $sitemap = Sitemap::create()
            ->add(Url::create('/')
                ->setLastModificationDate(now())
                ->setPriority(1.0)
                ->setChangeFrequency('daily'));

        // Add word pages to the sitemap
        Word::all()->each(function ($word) use ($sitemap) {
            $sitemap->add(Url::create(route('words.show', $word))
                ->setLastModificationDate($word->updated_at)
                ->setPriority(0.8)
                ->setChangeFrequency('weekly'));
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');
    }
}
