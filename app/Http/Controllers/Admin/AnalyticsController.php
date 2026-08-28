<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;

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
