<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleCategory;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * List active riddles (answers never exposed to the game client).
     */
    public function index(Request $request)
    {
        $query = Riddle::query()
            ->where('is_suspended', false)
            ->with('category:id,name,slug');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $riddles = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $riddles->map(fn (Riddle $riddle) => $this->gamePayload($riddle)),
        ]);
    }

    /**
     * Fetch a single ridder without revealing the answer.
     */
    public function show(Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $riddle->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($riddle),
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

        $solvedIds = $user->riddleAttempts()->where('is_correct', true)->pluck('riddle_id')->all();

        $unsolved = $riddles->reject(fn (Riddle $r) => in_array($r->id, $solvedIds, true))->values();

        $pool = $unsolved->isNotEmpty() ? $unsolved : $riddles;
        if ($pool->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No riddles available.'], 404);
        }

        $index = hexdec(substr($seed, 0, 8)) % $pool->count();
        $riddle = $pool[$index];
        $riddle->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($riddle),
        ]);
    }

    /**
     * Build the game-facing payload (answer is always omitted).
     */
    protected function gamePayload(Riddle $riddle): array
    {
        return [
            'id' => $riddle->id,
            'category' => $riddle->category
                ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                : null,
            'question' => $riddle->question,
            'hint' => $riddle->hint,
            'created_at' => $riddle->created_at,
        ];
    }
}
