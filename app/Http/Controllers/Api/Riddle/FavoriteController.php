<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Bookmarked riddles (game payload, answer never exposed).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $riddles = $user->favoriteRiddles()
            ->with(['category:id,name,slug', 'tags:id,name,slug'])
            ->orderByDesc('user_riddle_favorites.created_at')
            ->orderByDesc('user_riddle_favorites.id')
            ->get();

        $solvedIds = $user->riddleAttempts()
            ->where('is_correct', true)
            ->pluck('riddle_id')
            ->all();

        return response()->json([
            'success' => true,
            'data' => $riddles->map(fn (Riddle $riddle) => $this->gamePayload($riddle, in_array($riddle->id, $solvedIds, true))),
        ]);
    }

    /**
     * Bookmark a riddle (idempotent).
     */
    public function store(Request $request, Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $user = $request->user();
        if (! $user->favoriteRiddles()->where('riddle_id', $riddle->id)->exists()) {
            $user->favoriteRiddles()->attach($riddle->id);
        }

        return response()->json([
            'success' => true,
            'data' => ['favorited' => true, 'riddle_id' => $riddle->id],
        ]);
    }

    /**
     * Remove a bookmark (idempotent).
     */
    public function destroy(Request $request, Riddle $riddle)
    {
        $request->user()->favoriteRiddles()->detach($riddle->id);

        return response()->json([
            'success' => true,
            'data' => ['favorited' => false, 'riddle_id' => $riddle->id],
        ]);
    }

    /**
     * Game-facing payload (answer omitted).
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
