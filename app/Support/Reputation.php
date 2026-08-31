<?php

namespace App\Support;

use App\Models\JokeAttempt;
use App\Models\ProverbAttempt;
use App\Models\ReputationLog;
use App\Models\RiddleAttempt;
use App\Models\User;

/**
 * Shared reputation-award helpers.
 *
 * All "content" solve modes (riddles, proverbs, jokes) draw from a single
 * daily reputation cap so a player cannot farm points across modes.
 */
class Reputation
{
    /**
     * Attempt models whose correct solves earn reputation under the daily cap.
     *
     * @var array<int, class-string>
     */
    public const SOLVE_RELATED_TYPES = [
        RiddleAttempt::class,
        ProverbAttempt::class,
        JokeAttempt::class,
    ];

    /**
     * Reputation still available to earn today across all solve modes.
     */
    public static function dailyRemaining(User $user): int
    {
        $cap = (int) config('riddles.daily_solve_reputation_cap');
        if ($cap <= 0) {
            return 0;
        }

        $earnedToday = (int) ReputationLog::query()
            ->where('user_id', $user->id)
            ->whereIn('related_type', static::SOLVE_RELATED_TYPES)
            ->whereDate('created_at', now()->toDateString())
            ->sum('change');

        return max(0, $cap - $earnedToday);
    }
}
