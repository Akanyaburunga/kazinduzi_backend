<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Round;
use Illuminate\Http\Request;

class RoundHistoryController extends Controller
{
    /**
     * Aggregate the user's completed rounds into the History screen shape:
     * total points, number of games, best single-round score, and per-mode rows.
     */
    public function index(Request $request)
    {
        $rounds = Round::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Round::STATUS_COMPLETED)
            ->get();

        $rows = $rounds
            ->groupBy('mode')
            ->map(fn ($group, $mode) => [
                'mode' => $mode,
                'games' => $group->count(),
                'points' => $group->sum('score'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $rounds->sum('score'),
                'games' => $rounds->count(),
                'best' => $rounds->isEmpty() ? 0 : $rounds->max('score'),
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Reset the user's round history (hard delete; replica of the prototype's
     * localStorage reset). Lifetime reputation/attempts are preserved.
     */
    public function destroy(Request $request)
    {
        $deleted = Round::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['deleted' => $deleted],
        ]);
    }
}
