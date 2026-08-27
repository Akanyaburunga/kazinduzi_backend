<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\AnswerRiddleRequest;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Support\RiddleHelper;
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

        $normalizedSubmitted = RiddleHelper::normalize($request->answer);
        $normalizedAnswer = RiddleHelper::normalize($riddle->answer);

        $isCorrect = $normalizedSubmitted === $normalizedAnswer;

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
        if ($isCorrect && !$attempt->rewarded) {
            $points = (int) env('RIDDLE_SOLVE_REPUTATION', 5);
            $user->updateReputation($points, 'Solved a riddle', $attempt);
            $attempt->update(['rewarded' => true]);
            $rewarded = true;
        }

        return response()->json([
            'correct' => $isCorrect,
            'rewarded' => $rewarded,
            'message' => $isCorrect
                ? ($rewarded ? "Correct! You earned {$points} reputation points." : 'Correct!')
                : 'Not quite. Try again.',
        ]);
    }
}
