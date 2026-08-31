<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\AnswerRoundItemRequest;
use App\Models\JokeAttempt;
use App\Models\ProverbAttempt;
use App\Models\RiddleAttempt;
use App\Models\Round;
use App\Models\RoundItem;
use App\Support\Achievements;
use App\Support\AnswerMatcher;
use App\Support\Popularity;
use App\Support\Reputation;
use App\Support\RoundManager;
use App\Support\Streaks;
use Illuminate\Http\Request;

class RoundAnswerController extends Controller
{
    /**
     * Play one item in a round (answer a free-text riddle/proverb, or pick a
     * joke punchline).
     */
    public function answer(AnswerRoundItemRequest $request, string $mode, Round $round, int $position)
    {
        $this->authorizeRound($request, $mode, $round);

        $item = $round->items->firstWhere('position', $position);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Round item not found.'], 404);
        }

        // Idempotent for already-answered items: return the current state.
        if ($item->status !== RoundItem::STATUS_PENDING) {
            return $this->stateResponse($round, $request->user());
        }

        $puzzle = $item->puzzleModel();
        if (! $puzzle) {
            return response()->json(['success' => false, 'message' => 'Puzzle not available.'], 404);
        }

        $result = $this->evaluate($mode, $puzzle, $request);
        $item->increment('attempts');

        if ($result['conceded']) {
            $this->markConceded($item);
            $round->update(['current_streak' => 0]);
        } elseif ($result['correct']) {
            $item->update([
                'status' => RoundItem::STATUS_SOLVED,
                'is_correct' => true,
                'answered_at' => now(),
            ]);

            $award = $this->awardSolve(
                $mode,
                $request->user(),
                $puzzle,
                $mode === Round::MODE_TUJA ? (string) $request->option : (string) $request->answer
            );

            $streak = $round->current_streak + 1;
            $round->update([
                'score' => $round->score + 1,
                'current_streak' => $streak,
                'best_streak' => max($round->best_streak, $streak),
            ]);

            $result['rewarded'] = $award['rewarded'];
            $result['points'] = $award['points'];
            $result['capped'] = $award['capped'];
            $result['new_achievements'] = $award['new_achievements'];
        }

        if (! RoundManager::hasPendingItems($round->refresh())) {
            RoundManager::finalize($round);
        }

        return $this->stateResponse($round, $request->user(), $result);
    }

    /**
     * Skip a round item — behaves exactly like conceding.
     */
    public function skip(Request $request, string $mode, Round $round, int $position)
    {
        $this->authorizeRound($request, $mode, $round);

        $item = $round->items->firstWhere('position', $position);

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Round item not found.'], 404);
        }

        if ($item->status !== RoundItem::STATUS_PENDING) {
            return $this->stateResponse($round, $request->user());
        }

        $puzzle = $item->puzzleModel();
        $item->increment('attempts');
        $this->markConceded($item);
        $round->update(['current_streak' => 0]);

        if (! RoundManager::hasPendingItems($round->refresh())) {
            RoundManager::finalize($round);
        }

        $result = [
            'correct' => false,
            'conceded' => true,
            'answer' => $puzzle ? RoundManager::revealedAnswer($mode, $puzzle) : null,
            'message' => 'You skipped. The answer is revealed.',
            'rewarded' => false,
            'points' => 0,
            'capped' => false,
            'new_achievements' => [],
        ];

        return $this->stateResponse($round, $request->user(), $result);
    }

    /**
     * Ensure the round belongs to the user, matches the route mode, and is
     * still playable.
     */
    protected function authorizeRound(Request $request, string $mode, Round $round): void
    {
        abort_unless($round->user_id === $request->user()->id, 403);
        abort_unless($round->mode === $mode, 404);
        abort_if($round->isCompleted(), 422, 'Round already completed.');
    }

    /**
     * Evaluate the submitted answer/option for a mode.
     *
     * @return array{correct: bool, conceded: bool, answer: string|null, message: string}
     */
    protected function evaluate(string $mode, $puzzle, AnswerRoundItemRequest $request): array
    {
        if ($mode === Round::MODE_TUJA) {
            $option = trim((string) $request->option);
            $correct = $this->samePunchline($option, (string) $puzzle->punchline);

            if ($correct) {
                return [
                    'correct' => true,
                    'conceded' => false,
                    'answer' => null,
                    'message' => 'Correct!',
                ];
            }

            return [
                'correct' => false,
                'conceded' => true,
                'answer' => $puzzle->punchline,
                'message' => 'Not quite. The punchline is revealed.',
            ];
        }

        $candidates = trim((string) $puzzle->answer);
        if (! empty($puzzle->answer_aliases)) {
            $candidates .= ' / '.trim((string) $puzzle->answer_aliases);
        }

        $conceded = AnswerMatcher::isConcede((string) $request->answer);
        $correct = ! $conceded && AnswerMatcher::isCorrect((string) $request->answer, $candidates);

        return [
            'correct' => $correct,
            'conceded' => $conceded,
            'answer' => $conceded ? $puzzle->answer : null,
            'message' => $correct ? 'Correct!' : ($conceded ? 'You gave up. The answer is revealed.' : 'Not quite. Try again.'),
        ];
    }

    /**
     * Award reputation (once per puzzle) for a correct solve, mirroring the
     * mode-specific answer controllers.
     *
     * @return array{rewarded: bool, points: int, capped: bool, new_achievements: array}
     */
    protected function awardSolve(string $mode, $user, $puzzle, string $submitted): array
    {
        $attemptModel = $mode === Round::MODE_SOKWE
            ? RiddleAttempt::class
            : ($mode === Round::MODE_HERA ? ProverbAttempt::class : JokeAttempt::class);

        $key = $mode === Round::MODE_SOKWE ? 'riddle_id' : ($mode === Round::MODE_HERA ? 'proverb_id' : 'joke_id');
        $reason = $mode === Round::MODE_SOKWE ? 'Solved a riddle' : ($mode === Round::MODE_HERA ? 'Solved a proverb' : 'Solved a joke');

        $attempt = $attemptModel::updateOrCreate(
            ['user_id' => $user->id, $key => $puzzle->id],
            ['submitted_answer' => $submitted, 'is_correct' => true]
        );

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
                $user->updateReputation($points, $reason, $attempt);
                $attempt->update(['rewarded' => true]);
            }
            $rewarded = $points > 0;
        }

        $newAchievements = [];
        if ($mode === Round::MODE_SOKWE) {
            Streaks::recompute($user);
            Popularity::recompute($puzzle);
            $newAchievements = Achievements::evaluate($user)
                ->map(fn ($achievement) => [
                    'slug' => $achievement->slug,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                ])
                ->values()
                ->all();
        }

        return [
            'rewarded' => $rewarded,
            'points' => $points,
            'capped' => $capped,
            'new_achievements' => $newAchievements,
        ];
    }

    /**
     * Mark an item as conceded (revealing state). A conceded item is not a
     * correct solve, so it remains in the unsolved pool for later rounds.
     */
    protected function markConceded(RoundItem $item): void
    {
        $item->update([
            'status' => RoundItem::STATUS_CONCEDED,
            'is_correct' => false,
            'answered_at' => now(),
        ]);
    }

    /**
     * Normalized option === punchline comparison (jokes).
     */
    protected function samePunchline(string $option, string $punchline): bool
    {
        $norm = fn (string $v): string => \App\Support\RiddleHelper::normalize($v);

        return $norm($option) === $norm($punchline);
    }

    /**
     * Build the shared flat answer response.
     */
    protected function stateResponse(Round $round, $user, array $result = []): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            'correct' => $result['correct'] ?? false,
            'conceded' => $result['conceded'] ?? false,
            'answer' => $result['answer'] ?? null,
            'message' => $result['message'] ?? null,
            'rewarded' => $result['rewarded'] ?? false,
            'points' => $result['points'] ?? 0,
            'capped' => $result['capped'] ?? false,
            'round' => RoundManager::roundPayload($round, $user),
            'new_achievements' => $result['new_achievements'] ?? [],
        ]));
    }
}
