<?php

namespace App\Http\Controllers\Api\Proverb;

use App\Http\Controllers\Controller;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use Illuminate\Http\Request;

class ProverbGameController extends Controller
{
    /**
     * List active proverbs (answers never exposed to the game client).
     */
    public function index(Request $request)
    {
        $query = Proverb::query()
            ->where('is_suspended', false)
            ->with('category:id,name,slug');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('difficulty') && in_array($request->input('difficulty'), Proverb::DIFFICULTIES, true)) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $proverbs = $request->input('sort') === 'new'
            ? $query->latest('id')->get()
            : $query->latest()->get();

        $solvedIds = $this->solvedIds($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $proverbs->map(fn (Proverb $proverb) => $this->gamePayload($proverb, in_array($proverb->id, $solvedIds, true))),
        ]);
    }

    /**
     * Fetch a single proverb without revealing the answer.
     */
    public function show(Request $request, Proverb $proverb)
    {
        if ($proverb->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Proverb not available.'], 404);
        }

        $proverb->load('category:id,name,slug');

        $solved = $request->user()
            ->proverbAttempts()
            ->where('proverb_id', $proverb->id)
            ->where('is_correct', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($proverb, $solved),
        ]);
    }

    /**
     * Next unsolved active proverb, optionally filtered by difficulty.
     */
    public function next(Request $request)
    {
        $query = Proverb::where('is_suspended', false)->orderBy('id');

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->input('difficulty'));
        }

        $proverbs = $query->with('category:id,name,slug')->get();
        $solvedIds = $this->solvedIds($request->user()->id);

        $unsolved = $proverbs->reject(fn (Proverb $p) => in_array($p->id, $solvedIds, true))->values();

        if ($unsolved->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No unsolved proverbs available.'], 404);
        }

        $next = $unsolved->first();

        return response()->json([
            'success' => true,
            'data' => $this->gamePayload($next, false),
        ]);
    }

    /**
     * Learning endpoint: reveal the answer explicitly. No reputation change.
     */
    public function reveal(Proverb $proverb)
    {
        if ($proverb->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Proverb not available.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $proverb->id,
                'question' => $proverb->question,
                'answer' => $proverb->answer,
            ],
        ]);
    }

    /**
     * IDs of proverbs the user has correctly solved.
     */
    protected function solvedIds(int $userId): array
    {
        return ProverbAttempt::where('user_id', $userId)
            ->where('is_correct', true)
            ->pluck('proverb_id')
            ->all();
    }

    /**
     * Game-facing payload (answer is always omitted).
     */
    protected function gamePayload(Proverb $proverb, bool $solved = false): array
    {
        return [
            'id' => $proverb->id,
            'solved' => $solved,
            'category' => $proverb->category
                ? ['id' => $proverb->category->id, 'name' => $proverb->category->name, 'slug' => $proverb->category->slug]
                : null,
            'question' => $proverb->question,
            'difficulty' => $proverb->difficulty,
            'source' => $proverb->source,
            'created_at' => $proverb->created_at,
        ];
    }
}
