<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleHintUse;
use App\Models\User;
use App\Support\Streaks;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * List active riddles (answers never exposed to the game client).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Riddle::query()
            ->where('is_suspended', false)
            ->with(['category:id,name,slug', 'tags:id,name,slug']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('type') && in_array($request->input('type'), Riddle::RIDDLE_TYPES, true)) {
            $query->where('riddle_type', $request->input('type'));
        }

        if ($request->input('sort') === 'new') {
            $riddles = $query->latest('id')->get();
        } elseif ($request->input('sort') === 'trending') {
            $riddles = $query->orderByDesc('popularity_score')->get();
        } else {
            $riddles = $query->latest()->get();
        }

        $solvedIds = $this->solvedIds($user->id);

        return response()->json([
            'success' => true,
            'data' => $riddles->map(fn (Riddle $riddle) => $this->gamePayload($riddle, in_array($riddle->id, $solvedIds, true))),
        ]);
    }

    /**
     * Trending riddles ranked by popularity score (recency-weighted solves).
     */
    public function trending(Request $request)
    {
        $user = $request->user();

        $riddles = Riddle::query()
            ->where('is_suspended', false)
            ->with(['category:id,name,slug', 'tags:id,name,slug'])
            ->orderByDesc('popularity_score')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $solvedIds = $this->solvedIds($user->id);

        return response()->json([
            'success' => true,
            'data' => $riddles->map(fn (Riddle $riddle) => $this->gamePayload($riddle, in_array($riddle->id, $solvedIds, true))),
        ]);
    }


    /**
     * Fetch a single riddle without revealing the answer.
     */
    public function show(Request $request, Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $riddle->load('category:id,name,slug');

        $solved = $request->user()
            ->riddleAttempts()
            ->where('riddle_id', $riddle->id)
            ->where('is_correct', true)
            ->exists();

        $hintsRevealed = $this->hintsRevealed($request->user()->id, $riddle->id);

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($riddle, $solved, $hintsRevealed),
        ]);
    }

    /**
     * Riddle of the day: a deterministic pick per user/day that is
     * stable (unless already solved today, then a fallback active riddle).
     */
    public function daily(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        try {
            [$riddle, $solvedToday] = $this->resolveDaily($user, $today);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'No riddles available.'], 404);
        }

        $streaks = Streaks::compute($user);

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => [
                    'current' => $streaks['current'],
                    'longest' => $streaks['longest'],
                ],
                'solved_by_count' => $this->dailySolvedCount($riddle, $today),
                'best_streak' => (int) User::query()->max('longest_streak'),
                'daily' => $this->gamePayload($riddle, $solvedToday, $this->hintsRevealed($user->id, $riddle->id)),
            ],
        ]);
    }

    /**
     * Daily riddle archive: replay a past (or today's) daily riddle for a date.
     */
    public function dailyHistory(Request $request)
    {
        $user = $request->user();
        $date = $request->input('date') ?: now()->toDateString();

        try {
            [$riddle, $solved] = $this->resolveDaily($user, $date);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'No riddles available.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'solved' => $solved,
                'daily' => $this->gamePayload($riddle, $solved, $this->hintsRevealed($user->id, $riddle->id)),
            ],
        ]);
    }

    /**
     * Notifications badge data for the daily experience.
     */
    public function dailyStatus(Request $request)
    {
        $user = $request->user();
        $today = now()->startOfDay();

        $solvedToday = $user->riddleAttempts()
            ->where('is_correct', true)
            ->whereDate('created_at', $today->toDateString())
            ->exists();

        $frozenToday = $user->streak_freeze_date
            && $user->streak_freeze_date->format('Y-m-d') === $today->toDateString();

        $streaks = Streaks::compute($user);

        return response()->json([
            'success' => true,
            'data' => [
                'daily_available' => !$solvedToday,
                'streak_at_risk' => $streaks['current'] > 0 && !$solvedToday && !$frozenToday,
                'pending_challenges' => 0,
                'streak' => [
                    'current' => $streaks['current'],
                    'longest' => $streaks['longest'],
                ],
            ],
        ]);
    }

    /**
     * Spend one streak saver freeze to protect today's streak without a solve.
     */
    public function useStreakFreeze(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        if ($user->streak_freeze_date && $user->streak_freeze_date->format('Y-m-d') === $today) {
            return response()->json(['success' => false, 'message' => 'Streak freeze already used today.'], 422);
        }

        if ($user->streak_freezes <= 0) {
            return response()->json(['success' => false, 'message' => 'No streak freezes left.'], 422);
        }

        $user->forceFill([
            'streak_freezes' => $user->streak_freezes - 1,
            'streak_freeze_date' => $today,
        ])->save();

        $streaks = Streaks::recompute($user);

        return response()->json([
            'success' => true,
            'data' => [
                'freezes_remaining' => $user->streak_freezes,
                'freeze_active' => true,
                'streak' => [
                    'current' => $streaks['current'],
                    'longest' => $streaks['longest'],
                ],
            ],
        ]);
    }

    /**
     * Resolve the deterministic daily riddle for a user/date,
     * falling back to an unsolved active riddle when that pick is already solved.
     */
    private function resolveDaily(User $user, string $date): array
    {
        $seed = md5("{$user->id}-" . $date);
        $riddles = Riddle::where('is_suspended', false)->orderBy('id')->get();

        $solvedIds = $this->solvedIds($user->id);
        $unsolved = $riddles->reject(fn (Riddle $r) => in_array($r->id, $solvedIds, true))->values();

        $pool = $unsolved->isNotEmpty() ? $unsolved : $riddles;
        if ($pool->isEmpty()) {
            throw new ModelNotFoundException('No riddles available.');
        }

        $index = hexdec(substr($seed, 0, 8)) % $pool->count();
        $riddle = $pool[$index];
        $riddle->load('category:id,name,slug');

        return [$riddle, in_array($riddle->id, $solvedIds, true)];
    }

    /**
     * How many users correct-solved this riddle on the given calendar date.
     */
    private function dailySolvedCount(Riddle $riddle, string $date): int
    {
        return RiddleAttempt::query()
            ->where('riddle_id', $riddle->id)
            ->where('is_correct', true)
            ->whereDate('created_at', $date)
            ->count();
    }

    /**
     * Next unsolved active riddle, optionally filtered by difficulty.
     *
     * Personalization: unsolved riddles from categories the user has most
     * history with (attempts) are preferred, so the next pick leans toward
     * categories the user already engages with (or avoids when no history).
     */
    public function next(Request $request)
    {
        $user = $request->user();

        $query = Riddle::where('is_suspended', false)->orderBy('id');

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $riddles = $query->with('category:id,name,slug')->get();
        $solvedIds = $this->solvedIds($user->id);

        $unsolved = $riddles->reject(fn (Riddle $r) => in_array($r->id, $solvedIds, true))->values();

        if ($unsolved->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No unsolved riddles available.'], 404);
        }

        $affinity = $this->categoryAffinity($user->id);
        $next = $unsolved->sortBy(fn (Riddle $r) => $affinity[$r->category_id ?? 0] ?? -1, SORT_REGULAR, true)
            ->values()
            ->first();

        $next->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($next, false, $this->hintsRevealed($user->id, $next->id)),
        ]);
    }

    /**
     * Per-category attempt counts for a user, used to personalise "next".
     */
    private function categoryAffinity(int $userId): array
    {
        return RiddleAttempt::query()
            ->join('riddles', 'riddles.id', '=', 'riddle_attempts.riddle_id')
            ->where('riddle_attempts.user_id', $userId)
            ->groupBy('riddles.category_id')
            ->selectRaw('riddles.category_id, COUNT(*) as attempts')
            ->get()
            ->pluck('attempts', 'category_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Number of hints the user has revealed for a riddle (resume point).
     */
    private function hintsRevealed(int $userId, int $riddleId): int
    {
        return (int) \App\Models\UserRiddleProgress::query()
            ->where('user_id', $userId)
            ->where('riddle_id', $riddleId)
            ->value('hints_revealed');
    }

    /**
     * Progressive hint(s) for a riddle (never reveals the answer).
     * Records that the user used a hint for this riddle (for the "no hint"
     * achievement).
     */
    public function hint(Request $request, Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $userId = $request->user()->id;

        RiddleHintUse::query()->updateOrCreate(
            ['user_id' => $userId, 'riddle_id' => $riddle->id],
            ['count' => (int) RiddleHintUse::query()
                ->where('user_id', $userId)
                ->where('riddle_id', $riddle->id)
                ->value('count') + 1]
        );

        \App\Models\UserRiddleProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'riddle_id' => $riddle->id],
            [
                'hints_revealed' => 2,
                'last_hinted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $riddle->id,
                'hint' => $riddle->hint,
                'hint2' => $riddle->hint2,
                'hints_revealed' => 2,
            ],
        ]);
    }

    /**
     * Learning endpoint: reveal the answer explicitly. No reputation change.
     */
    public function reveal(Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $riddle->id,
                'question' => $riddle->question,
                'answer' => $riddle->answer,
            ],
        ]);
    }

    /**
     * Paginated attempt history for the authenticated user.
     */
    public function history(Request $request)
    {
        $attempts = $request->user()
            ->riddleAttempts()
            ->with('riddle.category:id,name,slug')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $attempts->map(fn (RiddleAttempt $attempt) => $this->attemptPayload($attempt)),
        ]);
    }

    /**
     * Aggregated attempt statistics for the authenticated user.
     */
    public function historyStats(Request $request)
    {
        $user = $request->user();

        $attempts = $user->riddleAttempts()->with('riddle.category')->get();

        $total = $attempts->count();
        $solved = $attempts->where('is_correct', true)->count();

        $byCategory = $attempts
            ->groupBy(fn (RiddleAttempt $a) => $a->riddle?->category_id ?? 'none')
            ->map(function ($group, $categoryId) {
                $category = $group->first()->riddle?->category;

                return [
                    'category_id' => $categoryId === 'none' ? null : (int) $categoryId,
                    'name' => $category?->name ?? 'Uncategorized',
                    'attempts' => $group->count(),
                    'solved' => $group->where('is_correct', true)->count(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_attempts' => $total,
                'riddles_solved' => $solved,
                'unique_riddles' => $attempts->pluck('riddle_id')->unique()->count(),
                'accuracy' => $total > 0 ? round(($solved / $total) * 100, 1) : 0,
                'by_category' => $byCategory,
            ],
        ]);
    }

    /**
     * IDs of riddles the user has correctly solved.
     */
    protected function solvedIds(int $userId): array
    {
        return RiddleAttempt::where('user_id', $userId)
            ->where('is_correct', true)
            ->pluck('riddle_id')
            ->all();
    }

    /**
     * Individual attempt history payload (riddle answer is never exposed).
     */
    protected function attemptPayload(RiddleAttempt $attempt): array
    {
        $riddle = $attempt->riddle;

        return [
            'id' => $attempt->id,
            'riddle' => $riddle ? [
                'id' => $riddle->id,
                'question' => $riddle->question,
                'difficulty' => $riddle->difficulty,
                'category' => $riddle->category
                    ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                    : null,
            ] : null,
            'submitted_answer' => $attempt->submitted_answer,
            'is_correct' => $attempt->is_correct,
            'rewarded' => $attempt->rewarded,
            'attempted_at' => $attempt->created_at,
        ];
    }

    /**
     * Build the game-facing payload (answer is always omitted unless revealed).
     */
    protected function gamePayload(Riddle $riddle, bool $solved = false, ?int $hintsRevealed = 0): array
    {
        return [
            'id' => $riddle->id,
            'solved' => $solved,
            'hints_revealed' => $hintsRevealed,
            'category' => $riddle->category
                ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                : null,
            'question' => $riddle->question,
            'difficulty' => $riddle->difficulty,
            'riddle_type' => $riddle->riddle_type,
            'tags' => $riddle->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'hint' => $riddle->hint,
            'hint2' => $riddle->hint2,
            'created_at' => $riddle->created_at,
        ];
    }
}
