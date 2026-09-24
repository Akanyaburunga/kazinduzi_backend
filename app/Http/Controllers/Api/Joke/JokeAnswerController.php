<?php

namespace App\Http\Controllers\Api\Joke;

use App\Http\Controllers\Controller;
use App\Http\Requests\Joke\AnswerJokeRequest;
use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Support\Reputation;

class JokeAnswerController extends Controller
{
    /**
     * Validate the chosen option against the joke's punchline and award
     * reputation exactly once per joke on the first correct pick.
     */
    public function store(AnswerJokeRequest $request, Joke $joke)
    {
        if ($joke->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Joke not available.'], 404);
        }

        $user = $request->user();

        $submitted = trim((string) $request->option);
        $isCorrect = $this->samePunchline($submitted, (string) $joke->punchline);

        $attempt = JokeAttempt::updateOrCreate(
            [
                'user_id' => $user->id,
                'joke_id' => $joke->id,
            ],
            [
                'submitted_answer' => $submitted,
                'is_correct' => $isCorrect,
            ]
        );

        if (! $isCorrect) {
            return response()->json([
                'success' => false,
                'message' => 'Not quite. The correct punchline is revealed.',
                'correct' => false,
                'answer' => $joke->punchline,
            ]);
        }

        $rewarded = false;
        $points = 0;
        $capped = false;
        if (! $attempt->rewarded) {
            $base = (int) config('riddles.solve_reputation');
            $cap = (int) config('riddles.daily_solve_reputation_cap');

            $remaining = Reputation::dailyRemaining($user);
            $points = $cap > 0 ? min($base, $remaining) : 0;
            $capped = $remaining <= 0;

            if ($points > 0) {
                $user->updateReputation($points, 'Solved a joke', $attempt);
                $attempt->update(['rewarded' => true]);
            }
            $rewarded = $points > 0;
        }

        return response()->json([
            'success' => true,
            'correct' => true,
            'rewarded' => $rewarded,
            'points' => $points,
            'capped' => $capped,
            'answer' => $joke->punchline,
            'message' => $rewarded
                ? "Correct! You earned {$points} reputation points."
                : ($capped ? 'Correct! You reached today’s reputation cap.' : 'Correct!'),
        ]);
    }

    /**
     * Compare an option with the punchline after light normalization.
     */
    protected function samePunchline(string $option, string $punchline): bool
    {
        $norm = fn (string $v): string => \App\Support\RiddleHelper::normalize($v);

        return $norm($option) === $norm($punchline);
    }
}