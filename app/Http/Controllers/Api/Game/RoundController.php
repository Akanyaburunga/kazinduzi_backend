<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StartRoundRequest;
use App\Models\Round;
use App\Support\GuestLimits;
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
                'guest' => $request->user()->isGuest()
                    ? GuestLimits::status($mode, $request->user())
                    : null,
            ],
        ]);
    }

    /**
     * Start a new round for a mode/level and return the first item.
     *
     * Guests may only start rounds while they are under their admin-set
     * per-mode free-round allowance; once exhausted they must register.
     */
    public function store(StartRoundRequest $request, string $mode)
    {
        $user = $request->user();
        $level = (int) $request->input('level', 1);

        if ($user->isGuest()) {
            $status = GuestLimits::status($mode, $user);

            if ($status['requires_registration']) {
                return GuestLimits::blockedResponse($mode, $user);
            }
        }

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
                'guest' => $user->isGuest() ? GuestLimits::status($mode, $user) : null,
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
     * Return one round item in its per-position state (Back navigation).
     *
     * Pending items never expose the answer; answered/conceded items disclose
     * the revealed answer plus correctness so the client can re-render the
     * solved state.
     */
    public function item(Request $request, string $mode, Round $round, int $position)
    {
        abort_unless($round->user_id === $request->user()->id, 403);
        abort_unless($round->mode === $mode, 404);

        $item = $round->items->firstWhere('position', $position);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Round item not found.'], 404);
        }

        $payload = RoundManager::itemPayload($item);

        if ($item->isAnswered()) {
            $payload['answered'] = true;
            $payload['answered_correct'] = (bool) $item->is_correct;
            $payload['revealed_answer'] = RoundManager::revealedAnswer($round->mode, $item->puzzleModel());
        } else {
            $payload['answered'] = false;
            $payload['answered_correct'] = false;
            $payload['revealed_answer'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => ['item' => $payload],
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
