<?php

namespace App\Http\Controllers\Api\Proverb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proverb\AnswerProverbRequest;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\Round;
use App\Support\AnswerMatcher;
use App\Support\GuestLimits;
use App\Support\Reputation;

class ProverbAnswerController extends Controller
{
    /**
     * Evaluate a user's answer, record the attempt, and award reputation
     * exactly once per proverb on the first correct solve (shared daily cap).
     */
    public function store(AnswerProverbRequest $request, Proverb $proverb)
    {
        if ($proverb->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Proverb not available.'], 404);
        }

        $user = $request->user();

        if ($user->isGuest()) {
            if (GuestLimits::requiresRegistration(Round::MODE_HERA, $user)) {
                return GuestLimits::blockedResponse(Round::MODE_HERA, $user);
            }

            GuestLimits::recordLegacyPlay(Round::MODE_HERA, $user, 'proverb', $proverb->id);
        }

        $candidates = trim((string) $proverb->answer);
        if (! empty($proverb->answer_aliases)) {
            $candidates .= ' / ' . trim((string) $proverb->answer_aliases);
        }

        $conceded = AnswerMatcher::isConcede((string) $request->answer);
        $isCorrect = ! $conceded && AnswerMatcher::isCorrect((string) $request->answer, $candidates);

        $attempt = ProverbAttempt::updateOrCreate(
            [
                'user_id' => $user->id,
                'proverb_id' => $proverb->id,
            ],
            [
                'submitted_answer' => $request->answer,
                'is_correct' => $isCorrect,
            ]
        );

        $rewarded = false;
        $points = 0;
        $capped = false;
        if ($isCorrect && ! $attempt->rewarded && ! $user->isGuest()) {
            $base = (int) config('riddles.solve_reputation');
            $cap = (int) config('riddles.daily_solve_reputation_cap');

            $remaining = Reputation::dailyRemaining($user);
            $points = $cap > 0 ? min($base, $remaining) : 0;
            $capped = $remaining <= 0;

            if ($points > 0) {
                $user->updateReputation($points, 'Solved a proverb', $attempt);
                $attempt->update(['rewarded' => true]);
            }
            $rewarded = $points > 0;
        }

        return response()->json([
            'correct' => $isCorrect,
            'rewarded' => $rewarded,
            'points' => $points,
            'capped' => $isCorrect && $capped,
            'conceded' => $conceded,
            'answer' => $conceded ? $proverb->answer : null,
            'message' => $isCorrect
                ? ($rewarded ? "Correct! You earned {$points} reputation points." : ($capped ? 'Correct! You reached today’s reputation cap.' : 'Correct!'))
                : ($conceded ? 'You gave up. The answer is revealed.' : 'Not quite. Try again.'),
        ]);
    }
}
