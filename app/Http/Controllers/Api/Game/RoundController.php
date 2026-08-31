<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StartRoundRequest;
use App\Models\Round;
use App\Support\RoundManager;
use Illuminate\Http\Request;

class RoundController extends Controller
{
    /**
     * Recent rounds for a mode (resume + resume-from-history).
     */
    public function index(Request $request, string $mode)
    {
        $rounds = Round::query()
            ->where('user_id', $request->user()->id)
            ->where('mode', $mode)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'rounds' => $rounds->map(
                    fn (Round $round) => RoundManager::roundPayload($round, $request->user())
                )->values(),
            ],
        ]);
    }

    /**
     * Start a new round for a mode/level and return the first item.
     */
    public function store(StartRoundRequest $request, string $mode)
    {
        $user = $request->user();
        $level = (int) $request->input('level', 1);

        $round = RoundManager::start($user, $mode, $level);

        if (! $round) {
            return response()->json([
                'success' => false,
                'message' => 'No unsolved items available for this mode.',
            ], 404);
        }

        $item = RoundManager::currentItem($round);

        return response()->json([
            'success' => true,
            'data' => [
                'round' => RoundManager::roundPayload($round, $user),
                'item' => $item ? RoundManager::itemPayload($item) : null,
            ],
        ]);
    }

    /**
     * Resume a round: return the current unfinished item (null once completed).
     */
    public function show(Request $request, string $mode, Round $round)
    {
        abort_unless($round->user_id === $request->user()->id, 403);
        abort_unless($round->mode === $mode, 404);

        $item = RoundManager::currentItem($round);

        return response()->json([
            'success' => true,
            'data' => [
                'round' => RoundManager::roundPayload($round, $request->user()),
                'item' => $item ? RoundManager::itemPayload($item) : null,
            ],
        ]);
    }

    /**
     * Explicitly finalize a round and return the end-state summary.
     */
    public function complete(Request $request, string $mode, Round $round)
    {
        abort_unless($round->user_id === $request->user()->id, 403);
        abort_unless($round->mode === $mode, 404);

        $round = RoundManager::finalize($round);

        return response()->json([
            'success' => true,
            'data' => [
                'round' => RoundManager::roundPayload($round, $request->user()),
                'performance' => static::performance($round->score, $round->item_count),
            ],
        ]);
    }

    /**
     * Prototype performance label for an end screen.
     */
    public static function performance(int $score, int $itemCount): string
    {
        return $score >= 8 ? 'top' : ($score >= 5 ? 'mid' : 'low');
    }
}
