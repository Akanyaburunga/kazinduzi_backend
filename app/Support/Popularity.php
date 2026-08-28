<?php

namespace App\Support;

use App\Models\Riddle;
use App\Models\RiddleAttempt;

class Popularity
{
    /**
     * Recompute and persist a riddle's popularity score, derived from its
     * correct-solve volume weighted toward recency so trending favours the
     * recently engaging riddles.
     */
    public static function recompute(Riddle $riddle): int
    {
        $total = $riddle->attempts()->where('is_correct', true)->count();
        $recent = RiddleAttempt::query()
            ->where('riddle_id', $riddle->id)
            ->where('is_correct', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $score = $total + (2 * $recent);

        $riddle->forceFill(['popularity_score' => $score])->save();

        return $score;
    }
}
