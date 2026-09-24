<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\JokeSubmission;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\ProverbSubmission;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\RiddleSubmission;
use App\Models\Round;
use Illuminate\Support\Collection;

class AnalyticsController extends Controller
{
    /**
     * Riddle performance by category, type and difficulty.
     */
    public function performance()
    {
        $base = RiddleAttempt::query()
            ->selectRaw('riddles.category_id, riddles.riddle_type, riddles.difficulty, count(*) as attempts, sum(case when is_correct then 1 else 0 end) as solves')
            ->join('riddles', 'riddles.id', '=', 'riddle_attempts.riddle_id')
            ->groupBy('riddles.category_id', 'riddles.riddle_type', 'riddles.difficulty');

        $rows = $base->get();

        $categories = RiddleCategory::withCount(['riddles' => fn ($q) => $q->where('is_suspended', false)])->get();
        $categorySolves = $rows->groupBy('category_id');

        $byCategory = $categories->map(function ($category) use ($categorySolves) {
            $stats = $this->aggregate($categorySolves->get($category->id));
            $stats['category_id'] = $category->id;
            $stats['name'] = $category->name;
            $stats['riddles'] = (int) $category->riddles_count;

            return $stats;
        })->values();

        $byType = $rows->groupBy('riddle_type')
            ->map(fn ($group, $type) => array_merge(['type' => $type], $this->aggregate($group)))
            ->values();

        $byDifficulty = $rows->groupBy('difficulty')
            ->map(fn ($group, $difficulty) => array_merge(['difficulty' => $difficulty], $this->aggregate($group)))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'by_category' => $byCategory,
                'by_type' => $byType,
                'by_difficulty' => $byDifficulty,
                'by_mode' => $this->byMode(),
                'category_by_mode' => $this->categoryByMode(),
            ],
        ]);
    }

    /**
     * Per-mode solve-rate overview across all three game queues.
     */
    private function byMode(): array
    {
        $modes = [
            Round::MODE_SOKWE => [Riddle::class, RiddleAttempt::class],
            Round::MODE_HERA => [Proverb::class, ProverbAttempt::class],
            Round::MODE_TUJA => [Joke::class, JokeAttempt::class],
        ];

        $rows = [];
        foreach ($modes as $mode => [$model, $attemptModel]) {
            $attempts = $attemptModel::count();
            $solves = $attemptModel::where('is_correct', true)->count();

            $rows[] = [
                'mode' => $mode,
                'label' => ucfirst($mode),
                'items' => $model::count(),
                'attempts' => (int) $attempts,
                'solves' => (int) $solves,
                'success_rate' => $attempts > 0 ? round(($solves / $attempts) * 100, 1) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Solve-rate by category, separately for each game mode.
     */
    private function categoryByMode(): array
    {
        $modes = [
            Round::MODE_SOKWE => ['attempt' => RiddleAttempt::class, 'attempt_table' => 'riddle_attempts', 'puzzle_table' => 'riddles', 'fk' => 'riddle_id'],
            Round::MODE_HERA => ['attempt' => ProverbAttempt::class, 'attempt_table' => 'proverb_attempts', 'puzzle_table' => 'proverbs', 'fk' => 'proverb_id'],
            Round::MODE_TUJA => ['attempt' => JokeAttempt::class, 'attempt_table' => 'joke_attempts', 'puzzle_table' => 'jokes', 'fk' => 'joke_id'],
        ];

        $categories = RiddleCategory::pluck('name', 'id');

        $result = [];
        foreach ($modes as $mode => $map) {
            $table = $map['puzzle_table'];

            $rows = $map['attempt']::query()
                ->selectRaw("{$table}.category_id, count(*) as attempts, sum(case when {$map['attempt_table']}.is_correct then 1 else 0 end) as solves")
                ->join($table, $table.'.id', '=', "{$map['attempt_table']}.{$map['fk']}")
                ->groupBy("{$table}.category_id")
                ->get();

            $result[$mode] = $rows->map(fn ($row) => [
                'category_id' => $row->category_id,
                'name' => $categories[$row->category_id] ?? 'No category',
                'attempts' => (int) $row->attempts,
                'solves' => (int) $row->solves,
                'success_rate' => $row->attempts > 0 ? round(($row->solves / $row->attempts) * 100, 1) : 0,
            ])->values();
        }

        return $result;
    }

    /**
     * Round-of-10 volume, completion and score by level over the window.
     */
    public function rounds()
    {
        $days = (int) request('days', 14);

        $perDay = Round::query()
            ->selectRaw('date(started_at) as day, count(*) as total, sum(case when status = ? then 1 else 0 end) as completed', [Round::STATUS_COMPLETED])
            ->where('started_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = collect(range($days - 1, 0))->map(function ($offset) use ($perDay) {
            $day = now()->subDays($offset)->toDateString();
            $row = $perDay->get($day);

            $total = (int) ($row->total ?? 0);
            $completed = (int) ($row->completed ?? 0);

            return [
                'day' => $day,
                'rounds' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->values();

        $scoreByLevel = Round::query()
            ->selectRaw('level, count(*) as rounds, avg(score) as avg_score, sum(case when status = ? then 1 else 0 end) as completed', [Round::STATUS_COMPLETED])
            ->groupBy('level')
            ->orderBy('level')
            ->get()
            ->map(fn ($row) => [
                'level' => (int) $row->level,
                'rounds' => (int) $row->rounds,
                'avg_score' => round((float) $row->avg_score, 1),
                'completed' => (int) $row->completed,
                'completion_rate' => $row->rounds > 0 ? round(($row->completed / $row->rounds) * 100, 1) : 0,
            ]);

        $total = Round::count();
        $levelUps = Round::where('level', '>', 1)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'daily_rounds' => $series,
                'total_rounds' => Round::count(),
                'completed_rounds' => Round::where('status', Round::STATUS_COMPLETED)->count(),
                'active_rounds' => Round::where('status', Round::STATUS_ACTIVE)->count(),
                'avg_score' => round((float) Round::avg('score'), 1),
                'completion_rate' => $total > 0 ? round((Round::where('status', Round::STATUS_COMPLETED)->count() / $total) * 100, 1) : 0,
                'level_up_rate' => $total > 0 ? round(($levelUps / $total) * 100, 1) : 0,
                'level_up_rounds' => $levelUps,
                'score_by_level' => $scoreByLevel,
                'rounds_by_mode' => Round::query()
                    ->selectRaw('mode, count(*) as count')
                    ->groupBy('mode')
                    ->get()
                    ->map(fn ($row) => ['mode' => $row->mode, 'count' => (int) $row->count]),
            ],
        ]);
    }

    /**
     * Moderation funnel across the three contribution queues.
     */
    public function contributions()
    {
        $queues = [
            'riddles' => RiddleSubmission::class,
            'proverbs' => ProverbSubmission::class,
            'jokes' => JokeSubmission::class,
        ];

        $build = function ($class) {
            $counts = $class::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ];
        };

        $byQueue = [];
        $totals = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        foreach ($queues as $queue => $class) {
            $byQueue[$queue] = $build($class);
            foreach ($totals as $status => $_) {
                $totals[$status] += $byQueue[$queue][$status];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'by_queue' => $byQueue,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * Daily-active players over the trailing window.
     */
    public function players()
    {
        $days = (int) request('days', 14);

        $rows = RiddleAttempt::query()
            ->selectRaw('date(created_at) as day, count(distinct user_id) as active_users')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->pluck('active_users', 'day');

        $series = collect(range($days - 1, 0))->mapWithKeys(function ($offset) use ($rows) {
            $day = now()->subDays($offset)->toDateString();

            return [$day => (int) ($rows[$day] ?? 0)];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'daily_active_players' => $series,
            ],
        ]);
    }

    /**
     * Conversion of the daily challenge: active users -> daily solvers.
     */
    public function dailyConversion()
    {
        $days = (int) request('days', 14);

        $rows = RiddleAttempt::query()
            ->selectRaw('date(created_at) as day, count(distinct user_id) as active_users, count(distinct case when is_correct then user_id end) as solvers')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = collect(range($days - 1, 0))->map(function ($offset) use ($rows) {
            $day = now()->subDays($offset)->toDateString();
            $row = $rows->get($day);

            $active = (int) ($row->active_users ?? 0);
            $solvers = (int) ($row->solvers ?? 0);

            return [
                'day' => $day,
                'active_users' => $active,
                'solvers' => $solvers,
                'conversion_rate' => $active > 0 ? round(($solvers / $active) * 100, 1) : 0,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'daily_conversion' => $series,
            ],
        ]);
    }

    /**
     * Sum attempt/solve counts and derive a success rate for a group.
     */
    private function aggregate($group): array
    {
        $group = $group ?? collect();

        $attempts = (int) $group->sum('attempts');
        $solves = (int) $group->sum('solves');

        return [
            'attempts' => $attempts,
            'solves' => $solves,
            'success_rate' => $attempts > 0 ? round(($solves / $attempts) * 100, 1) : 0,
        ];
    }
}
