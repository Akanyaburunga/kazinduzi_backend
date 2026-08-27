<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;

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
            ],
        ]);
    }
}
