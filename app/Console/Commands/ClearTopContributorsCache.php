<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearTopContributorsCache extends Command
{
    protected $signature = 'cache:clear-top-contributors';
    protected $description = 'Clear the top contributors cache';

    public function handle()
    {
        Cache::forget('top_contributors');
        $this->info('Top contributors cache cleared successfully.');
    }
}
