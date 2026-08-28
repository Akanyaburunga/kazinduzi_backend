<?php

namespace App\Console\Commands;

use App\Models\Challenge;
use App\Support\Duels;
use Illuminate\Console\Command;

class ExpireStaleDuels extends Command
{
    protected $signature = 'duels:expire-stale';
    protected $description = 'Expire pending duels not accepted in time and settle accepted duels left unfinished';

    public function handle(): int
    {
        $staleHours = (int) config('riddles.duel_stale_hours');
        $cutoff = now()->subHours($staleHours);

        $expired = Challenge::where('status', Challenge::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => Challenge::STATUS_EXPIRED, 'resolved_at' => now()]);

        $settled = 0;
        Challenge::where('status', Challenge::STATUS_ACCEPTED)
            ->where('created_at', '<', $cutoff)
            ->get()
            ->each(function (Challenge $challenge) use (&$settled) {
                Duels::settle($challenge);
                $settled++;
            });

        $this->info("Expired {$expired} pending duel(s); settled {$settled} unfinished duel(s).");

        return self::SUCCESS;
    }
}
