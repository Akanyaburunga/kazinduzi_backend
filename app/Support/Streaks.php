<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class Streaks
{
    /**
     * Current consecutive-day streak (ending today or yesterday).
     */
    public static function current(User $user, ?Carbon $today = null): int
    {
        return self::compute($user, $today)['current'];
    }

    /**
     * Longest consecutive-day run on record.
     */
    public static function longest(User $user, ?Carbon $today = null): int
    {
        return self::compute($user, $today)['longest'];
    }

    /**
     * Recompute from raw attempts and persist denormalized columns.
     */
    public static function recompute(User $user, ?Carbon $today = null): array
    {
        $result = self::compute($user, $today);

        $user->forceFill([
            'current_streak' => $result['current'],
            'longest_streak' => $result['longest'],
        ])->save();

        return $result;
    }

    /**
     * Derive current + longest streak from the user's correct-solve days.
     *
     * A "day" counts only if it has at least one correct solve. The current
     * streak is counted from today backwards, or from yesterday backwards
     * when today has not yet been solved (so the streak persists until the
     * day is lost).
     */
    public static function compute(User $user, ?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();

        $daySet = $user->riddleAttempts()
            ->where('is_correct', true)
            ->pluck('created_at')
            ->map(fn ($createdAt) => $createdAt instanceof Carbon
                ? $createdAt->startOfDay()->format('Y-m-d')
                : Carbon::parse($createdAt)->startOfDay()->format('Y-m-d'))
            ->unique()
            ->sort()
            ->all();

        // Longest consecutive run anywhere in history.
        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($daySet as $date) {
            $current = Carbon::parse($date)->startOfDay();
            $consecutive = $prev !== null && $current->copy()->subDay()->equalTo($prev);
            $run = $consecutive ? $run + 1 : 1;
            if ($run > $longest) {
                $longest = $run;
            }
            $prev = $current;
        }

        // Current streak: anchor on today if solved (or today is protected by
        // a streak freeze), else yesterday; walk backwards.
        $todayKey = $today->format('Y-m-d');
        $frozenToday = $user->streak_freeze_date
            && $user->streak_freeze_date->format('Y-m-d') === $todayKey;

        // A freeze applied today protects today: treat it as a covered day so
        // the run continues instead of resetting.
        $daySetEffective = $daySet;
        if ($frozenToday && !in_array($todayKey, $daySetEffective, true)) {
            $daySetEffective[] = $todayKey;
            sort($daySetEffective);
        }

        $anchor = in_array($todayKey, $daySetEffective, true)
            ? $today->copy()
            : $today->copy()->subDay();

        $current = 0;
        $cursor = $anchor;
        while (in_array($cursor->format('Y-m-d'), $daySetEffective, true)) {
            $current++;
            $cursor->subDay();
        }

        return ['current' => $current, 'longest' => $longest];
    }
}
