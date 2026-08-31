<?php

namespace App\Http\Controllers\Api\Joke;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use App\Models\JokeAttempt;
use Illuminate\Http\Request;

class JokeGameController extends Controller
{
    /**
     * Serve one round: a random active (preferably unsolved) joke with 4
     * shuffled options — the correct punchline plus three distractors drawn
     * from other jokes' punchlines. The client cannot infer the answer from
     * ordering.
     */
    public function round(Request $request)
    {
        $user = $request->user();

        $jokes = Joke::where('is_suspended', false)->get();
        $solvedIds = $this->solvedIds($user->id);

        $pool = $jokes->reject(fn (Joke $j) => in_array($j->id, $solvedIds, true))->values();
        if ($pool->isNotEmpty()) {
            $joke = $pool->random();
        } elseif ($jokes->isNotEmpty()) {
            $joke = $jokes->random();
        } else {
            return response()->json(['success' => false, 'message' => 'No jokes available.'], 404);
        }

        $options = $this->optionsFor($joke);

        return response()->json([
            'success' => true,
            'data' => [
                'joke_id' => $joke->id,
                'setup' => $joke->setup,
                'options' => $options,
            ],
        ]);
    }

    /**
     * Next unsolved setup, 404 when the player has solved them all.
     */
    public function next(Request $request)
    {
        $jokes = Joke::where('is_suspended', false)->orderBy('id')->get();
        $solvedIds = $this->solvedIds($request->user()->id);

        $unsolved = $jokes->reject(fn (Joke $j) => in_array($j->id, $solvedIds, true))->values();

        if ($unsolved->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No unsolved jokes available.'], 404);
        }

        $joke = $unsolved->first();

        return response()->json([
            'success' => true,
            'data' => [
                'joke_id' => $joke->id,
                'setup' => $joke->setup,
                'options' => $this->optionsFor($joke),
            ],
        ]);
    }

    /**
     * Learning endpoint: reveal the correct punchline. No reputation change.
     */
    public function reveal(Joke $joke)
    {
        if ($joke->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Joke not available.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'joke_id' => $joke->id,
                'setup' => $joke->setup,
                'answer' => $joke->punchline,
            ],
        ]);
    }

    /**
     * The four displayed options for a joke: punchline + 3 distractors,
     * shuffled server-side (correct one appears exactly once).
     *
     * @return array<int, string>
     */
    protected function optionsFor(Joke $joke): array
    {
        $distractors = (array) ($joke->distractors ?? []);
        $options = array_merge([$joke->punchline], $distractors);

        $pad = Joke::where('is_suspended', false)
            ->where('id', '!=', $joke->id)
            ->pluck('punchline')
            ->reject(fn ($p) => in_array($p, $options, true))
            ->shuffle()
            ->take(max(0, 4 - count($options)))
            ->values()
            ->all();

        $options = array_merge($options, $pad);
        shuffle($options);

        return array_values(array_unique($options));
    }

    /**
     * IDs of jokes the user has correctly solved.
     */
    protected function solvedIds(int $userId): array
    {
        return JokeAttempt::where('user_id', $userId)
            ->where('is_correct', true)
            ->pluck('joke_id')
            ->all();
    }
}