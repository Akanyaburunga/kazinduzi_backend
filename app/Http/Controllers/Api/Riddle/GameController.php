<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Support\Streaks;
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
            ->with('category:id,name,slug');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $riddles = $query->latest()->get();

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

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($riddle, $solved),
        ]);
    }

    /**
     * Riddle of the day: a deterministic pick per user/day that is
     * stable (unless already solved today, then a fallback active riddle).
     */
    public function daily(Request $request)
    {
        $user = $request->user();

        $seed = md5("{$user->id}-" . now()->toDateString());
        $riddles = Riddle::where('is_suspended', false)->orderBy('id')->get();

        $solvedIds = $this->solvedIds($user->id);

        $unsolved = $riddles->reject(fn (Riddle $r) => in_array($r->id, $solvedIds, true))->values();

        $pool = $unsolved->isNotEmpty() ? $unsolved : $riddles;
        if ($pool->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No riddles available.'], 404);
        }

        $index = hexdec(substr($seed, 0, 8)) % $pool->count();
        $riddle = $pool[$index];
        $riddle->load('category:id,name,slug');

        $solvedToday = in_array($riddle->id, $solvedIds, true);
        $streaks = Streaks::compute($user);

        return response()->json([
            'success' => true,
            'data' => [
                'streak' => [
                    'current' => $streaks['current'],
                    'longest' => $streaks['longest'],
                ],
                'daily' => $this->gamePayload($riddle, $solvedToday),
            ],
        ]);
    }

    /**
     * Next unsolved active riddle, optionally filtered by difficulty.
     */
    public function next(Request $request)
    {
        $user = $request->user();

        $query = Riddle::where('is_suspended', false)->orderBy('id');

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $riddles = $query->get();
        $solvedIds = $this->solvedIds($user->id);

        $next = $riddles
            ->reject(fn (Riddle $r) => in_array($r->id, $solvedIds, true))
            ->first();

        if (! $next) {
            return response()->json(['success' => false, 'message' => 'No unsolved riddles available.'], 404);
        }

        $next->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($next, false),
        ]);
    }

    /**
     * Progressive hint(s) for a riddle (never reveals the answer).
     */
    public function hint(Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $riddle->id,
                'hint' => $riddle->hint,
                'hint2' => $riddle->hint2,
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
    protected function gamePayload(Riddle $riddle, bool $solved = false): array
    {
        return [
            'id' => $riddle->id,
            'solved' => $solved,
            'category' => $riddle->category
                ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                : null,
            'question' => $riddle->question,
            'difficulty' => $riddle->difficulty,
            'hint' => $riddle->hint,
            'hint2' => $riddle->hint2,
            'created_at' => $riddle->created_at,
        ];
    }
}
