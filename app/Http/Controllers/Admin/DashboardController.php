<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\Round;

class DashboardController extends Controller
{
    /**
     * Aggregate stats for the admin dashboard.
     */
    public function index()
    {
        $today = now()->startOfDay();
        $solves = RiddleAttempt::where('is_correct', true);

        $topRiddles = Riddle::query()
            ->with('category:id,name')
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)])
            ->orderByDesc('solved_count')
            ->take(5)
            ->get(['id', 'question', 'category_id']);

        $difficultyBreakdown = Riddle::query()
            ->selectRaw('difficulty, count(*) as total, sum(case when is_suspended then 1 else 0 end) as suspended')
            ->groupBy('difficulty')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_riddles' => Riddle::count(),
                'suspended_riddles' => Riddle::where('is_suspended', true)->count(),
                'total_categories' => RiddleCategory::count(),
                'total_attempts' => RiddleAttempt::count(),
                'correct_attempts' => $solves->count(),
                'total_solves' => $solves->count(),
                'today_solves' => RiddleAttempt::where('is_correct', true)->where('created_at', '>=', $today)->count(),
                'today_attempts' => RiddleAttempt::where('created_at', '>=', $today)->count(),
                'active_players' => RiddleAttempt::distinct('user_id')->count('user_id'),
                'today_solvers' => RiddleAttempt::where('is_correct', true)
                    ->where('created_at', '>=', $today)
                    ->distinct('user_id')
                    ->count('user_id'),
                'top_riddles' => $topRiddles->map(fn ($r) => [
                    'id' => $r->id,
                    'question' => $r->question,
                    'category' => $r->category?->name,
                    'solved_count' => $r->solved_count,
                    'attempts_count' => $r->attempts_count,
                ]),
                'difficulty_breakdown' => $difficultyBreakdown->map(fn ($d) => [
                    'difficulty' => $d->difficulty,
                    'total' => (int) $d->total,
                    'suspended' => (int) $d->suspended,
                ]),
                'by_mode' => $this->modeSummaries($today),
                'round_stats' => $this->roundStats($today),
            ],
        ]);
    }

    /**
     * Per-mode inventory and activity: items, attempts, solves, today's solves.
     */
    private function modeSummaries($today): array
    {
        $modes = [
            Round::MODE_SOKWE => [Riddle::class, RiddleAttempt::class],
            Round::MODE_HERA => [Proverb::class, ProverbAttempt::class],
            Round::MODE_TUJA => [Joke::class, JokeAttempt::class],
        ];

        $summaries = [];
        foreach ($modes as $mode => [$model, $attemptModel]) {
            $attempts = $attemptModel::query();
            $solves = $attemptModel::where('is_correct', true);

            $summaries[] = [
                'mode' => $mode,
                'label' => ucfirst($mode),
                'items' => $model::where('is_suspended', false)->count(),
                'attempts' => (clone $attempts)->count(),
                'solves' => (clone $solves)->count(),
                'today_solves' => (clone $solves)->where('created_at', '>=', $today)->count(),
            ];
        }

        return $summaries;
    }

    /**
     * Round-of-10 health: volume, activity, completion and score by level.
     */
    private function roundStats($today): array
    {
        $scoreByLevel = Round::query()
            ->selectRaw('level, count(*) as rounds, avg(score) as avg_score, sum(case when status = ? then 1 else 0 end) as completed', [Round::STATUS_COMPLETED])
            ->groupBy('level')
            ->orderBy('level')
            ->get()
            ->map(fn ($r) => [
                'level' => (int) $r->level,
                'rounds' => (int) $r->rounds,
                'avg_score' => round((float) $r->avg_score, 1),
                'completed' => (int) $r->completed,
            ]);

        $roundsByMode = Round::query()
            ->selectRaw('mode, count(*) as count')
            ->groupBy('mode')
            ->get()
            ->map(fn ($r) => ['mode' => $r->mode, 'count' => (int) $r->count]);

        return [
            'total_rounds' => Round::count(),
            'active_rounds' => Round::where('status', Round::STATUS_ACTIVE)->count(),
            'completed_rounds' => Round::where('status', Round::STATUS_COMPLETED)->count(),
            'rounds_today' => Round::where('started_at', '>=', $today)->count(),
            'avg_score' => round((float) Round::avg('score'), 1),
            'score_by_level' => $scoreByLevel,
            'rounds_by_mode' => $roundsByMode,
            'started_last_7d' => Round::where('started_at', '>=', now()->subDays(6)->startOfDay())->count(),
            'started_last_30d' => Round::where('started_at', '>=', now()->subDays(29)->startOfDay())->count(),
        ];
    }
}