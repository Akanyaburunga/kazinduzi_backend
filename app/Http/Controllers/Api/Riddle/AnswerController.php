<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\AnswerRiddleRequest;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\ReputationLog;
use App\Support\Achievements;
use App\Support\AnswerMatcher;
use Illuminate\Http\Request;

class AnswerController extends Controller
{
    /**
     * Evaluate a user's answer, record the attempt, and award reputation
     * exactly once per riddle on the first correct solve.
     */
    public function store(AnswerRiddleRequest $request, Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $user = $request->user();

        $candidates = trim((string) $riddle->answer);
        if (! empty($riddle->answer_aliases)) {
            $candidates .= ' / ' . trim((string) $riddle->answer_aliases);
        }

        $conceded = AnswerMatcher::isConcede((string) $request->answer);
        $isCorrect = ! $conceded && AnswerMatcher::isCorrect((string) $request->answer, $candidates);

        $attempt = RiddleAttempt::updateOrCreate(
            [
                'user_id' => $user->id,
                'riddle_id' => $riddle->id,
            ],
            [
                'submitted_answer' => $request->answer,
                'is_correct' => $isCorrect,
            ]
        );

        $rewarded = false;
        $points = 0;
        $capped = false;
        if ($isCorrect && !$attempt->rewarded) {
            $base = (int) config('riddles.solve_reputation');
            $cap = (int) config('riddles.daily_solve_reputation_cap');

            $earnedToday = (int) ReputationLog::where('user_id', $user->id)
                ->where('related_type', RiddleAttempt::class)
                ->whereDate('created_at', now()->toDateString())
                ->sum('change');

            $remaining = max(0, $cap - $earnedToday);
            $points = $cap > 0 ? min($base, $remaining) : 0;
            $capped = $remaining <= 0;

            if ($points > 0) {
                $user->updateReputation($points, 'Solved a riddle', $attempt);
                $attempt->update(['rewarded' => true]);
            }
            $rewarded = $points > 0;
        }

        if ($isCorrect) {
            \App\Support\Streaks::recompute($user);
            \App\Support\Popularity::recompute($riddle);
        }

        $newAchievements = $isCorrect ? Achievements::evaluate($user) : collect();

        return response()->json([
            'correct' => $isCorrect,
            'rewarded' => $rewarded,
            'points' => $points,
            'capped' => $isCorrect && $capped,
            'conceded' => $conceded,
            'answer' => $conceded ? $riddle->answer : null,
            'message' => $isCorrect
                ? ($rewarded ? "Correct! You earned {$points} reputation points." : ($capped ? 'Correct! You reached today’s reputation cap.' : 'Correct!'))
                : ($conceded ? 'You gave up. The answer is revealed.' : 'Not quite. Try again.'),
            'new_achievements' => $newAchievements->map(fn ($achievement) => [
                'slug' => $achievement->slug,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
            ])->values(),
        ]);
    }
}
