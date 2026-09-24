<?php

namespace App\Support;

use App\Models\Achievement;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\RiddleHintUse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Badge (achievement) catalogue, progress evaluation and idempotent unlock.
 *
 * Unlocks are evaluated after each correct solve (see AnswerController);
 * each badge can only be earned once thanks to the user_achievements
 * unique constraint.
 */
class Achievements
{
    /**
     * Default badge catalogue, seeded on demand via syncCatalogue().
     *
     * @var array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            ['slug' => 'first_riddle',    'name' => 'First Riddle',    'description' => 'Solve your first riddle.',                       'category' => 'solved',  'metric' => 'solved',          'threshold' => 1,  'sort_order' => 10, 'icon' => 'solve'],
            ['slug' => 'riddles_10',      'name' => 'Ten Solved',       'description' => 'Correctly solve 10 different riddles.',          'category' => 'solved',  'metric' => 'solved',          'threshold' => 10, 'sort_order' => 20, 'icon' => 'solve'],
            ['slug' => 'riddles_50',      'name' => 'Fifty Solved',     'description' => 'Correctly solve 50 different riddles.',          'category' => 'solved',  'metric' => 'solved',          'threshold' => 50, 'sort_order' => 30, 'icon' => 'solve'],
            ['slug' => 'riddles_100',     'name' => 'Centenary',        'description' => 'Correctly solve 100 different riddles.',         'category' => 'solved',  'metric' => 'solved',          'threshold' => 100, 'sort_order' => 40, 'icon' => 'solve'],
            ['slug' => 'no_hint',         'name' => 'Pure Genius',      'description' => 'Solve a riddle without using any hint.',          'category' => 'solved',  'metric' => 'no_hint',         'threshold' => 1,  'sort_order' => 50, 'icon' => 'mind'],
            ['slug' => 'streak_3',        'name' => 'On a Roll',        'description' => 'Reach a 3-day daily streak.',                      'category' => 'streak',  'metric' => 'streak',          'threshold' => 3,  'sort_order' => 60, 'icon' => 'streak'],
            ['slug' => 'streak_7',        'name' => 'Weekly Sage',      'description' => 'Reach a 7-day daily streak.',                      'category' => 'streak',  'metric' => 'streak',          'threshold' => 7,  'sort_order' => 70, 'icon' => 'streak'],
            ['slug' => 'streak_30',       'name' => 'Unstoppable',      'description' => 'Reach a 30-day daily streak.',                     'category' => 'streak',  'metric' => 'streak',          'threshold' => 30, 'sort_order' => 80, 'icon' => 'streak'],
            ['slug' => 'category_master', 'name' => 'Category Master',  'description' => 'Master every riddle in at least one category.',     'category' => 'mastery', 'metric' => 'category_master',  'threshold' => 1,  'sort_order' => 90, 'icon' => 'master'],
            ['slug' => 'daily_champion',  'name' => 'Daily Champion',   'description' => 'Solve the daily riddle on 7 different days.',       'category' => 'daily',   'metric' => 'daily_champion',  'threshold' => 7,  'sort_order' => 100, 'icon' => 'daily'],
        ];
    }

    /**
     * Ensure the catalogue rows exist (seed on demand). Returns the catalogue.
     */
    public static function syncCatalogue(): Collection
    {
        foreach (static::definitions() as $definition) {
            Achievement::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition
            );
        }

        return static::catalogue();
    }

    /**
     * Active badges, ordered for display.
     */
    public static function catalogue(): Collection
    {
        return Achievement::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Current value of an achievement metric for a user.
     */
    public static function progress(User $user, string $metric): int
    {
        return match ($metric) {
            'solved' => static::solvedCount($user),
            'no_hint' => static::noHintSolves($user),
            'streak' => (int) $user->longest_streak,
            'category_master' => static::masteredCategories($user),
            'daily_champion' => static::dailyChampionDays($user),
            default => 0,
        };
    }

    /**
     * Number of distinct riddles correctly solved (once per riddle).
     */
    public static function solvedCount(User $user): int
    {
        return RiddleAttempt::query()
            ->where('user_id', $user->id)
            ->where('is_correct', true)
            ->distinct()
            ->count('riddle_id');
    }

    /**
     * Distinct correct solves where the user never requested a hint.
     */
    public static function noHintSolves(User $user): int
    {
        $hintedRiddleIds = RiddleHintUse::query()
            ->where('user_id', $user->id)
            ->pluck('riddle_id');

        $query = RiddleAttempt::query()
            ->where('user_id', $user->id)
            ->where('is_correct', true);

        if ($hintedRiddleIds->isNotEmpty()) {
            $query->whereNotIn('riddle_id', $hintedRiddleIds);
        }

        return $query->distinct()->count('riddle_id');
    }

    /**
     * Number of categories in which the user has solved every active riddle.
     */
    public static function masteredCategories(User $user): int
    {
        $solvedIds = RiddleAttempt::query()
            ->where('user_id', $user->id)
            ->where('is_correct', true)
            ->distinct()
            ->pluck('riddle_id');

        $mastered = 0;
        foreach (RiddleCategory::query()->pluck('id') as $categoryId) {
            $activeIds = Riddle::query()
                ->where('category_id', $categoryId)
                ->where('is_suspended', false)
                ->pluck('id');

            if ($activeIds->isEmpty()) {
                continue;
            }

            if ($activeIds->every(fn ($id) => $solvedIds->contains($id))) {
                $mastered++;
            }
        }

        return $mastered;
    }

    /**
     * Number of distinct days on which the user solved that day's daily riddle.
     *
     * Daily resolution uses the riddles solved *up to* each date (not the full
     * current set) so a past day is judged by what was "the daily" back then.
     */
    public static function dailyChampionDays(User $user): int
    {
        $attempts = RiddleAttempt::query()
            ->where('user_id', $user->id)
            ->where('is_correct', true)
            ->orderBy('created_at')
            ->get(['riddle_id', 'created_at']);

        if ($attempts->isEmpty()) {
            return 0;
        }

        $days = 0;
        $seenDates = [];
        $runningSolved = collect();

        foreach ($attempts as $attempt) {
            $date = Carbon::parse($attempt->created_at)->toDateString();
            $dailyId = static::dailyRiddleIdFor($user->id, $date, $runningSolved);

            if (
                $dailyId !== null
                && (int) $attempt->riddle_id === $dailyId
                && !in_array($date, $seenDates, true)
            ) {
                $days++;
                $seenDates[] = $date;
            }

            $runningSolved->push($attempt->riddle_id);
        }

        return $days;
    }

    /**
     * Deterministic daily riddle id for a user/date (mirrors GameController).
     * Optionally pass the solved set as it stood at that date.
     *
     * @internal exposed for testing
     */
    public static function dailyRiddleIdFor(int $userId, string $date, ?Collection $solvedIds = null): ?int
    {
        $riddles = Riddle::query()
            ->where('is_suspended', false)
            ->orderBy('id')
            ->pluck('id');

        if ($riddles->isEmpty()) {
            return null;
        }

        $solved = $solvedIds
            ?? RiddleAttempt::query()
                ->where('user_id', $userId)
                ->where('is_correct', true)
                ->pluck('riddle_id');

        $unsolved = $riddles->diff($solved)->values();
        $pool = $unsolved->isNotEmpty() ? $unsolved : $riddles;

        $seed = md5("{$userId}-" . $date);
        $index = hexdec(substr($seed, 0, 8)) % $pool->count();

        return (int) $pool[$index];
    }

    /**
     * Run the unlock evaluator after a solve. Idempotent: a badge is only
     * recorded once. Returns the newly unlocked achievements.
     *
     * @return Collection<int, Achievement>
     */
    public static function evaluate(User $user): Collection
    {
        $unlocked = collect();

        foreach (static::catalogue() as $achievement) {
            if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
                continue;
            }

            if (static::progress($user, $achievement->metric) >= $achievement->threshold) {
                $user->achievements()->syncWithoutDetaching([
                    $achievement->id => ['unlocked_at' => now()],
                ]);
                $unlocked->push($achievement);
            }
        }

        return $unlocked;
    }
}
