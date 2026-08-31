<?php

namespace App\Support;

use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Server-side "round of 10" manager for the rinjora-parity experience.
 *
 * Owns the pairing of game-mode -> puzzle table, the tiered pool build
 * (mirroring RinjoraTier::poolFor), round lifecycle (start / current item /
 * finalize) and the serialized payloads sent to the client.
 */
class RoundManager
{
    /**
     * Game mode -> puzzle-table mapping.
     *
     * @var array<string, array{type: string, model: class-string, attempt: class-string, attempt_key: string, question: string, answer: string}>
     */
    public const MODE_MAP = [
        Round::MODE_SOKWE => [
            'type' => RoundItem::PUZZLE_RIDDLE,
            'model' => Riddle::class,
            'attempt' => RiddleAttempt::class,
            'attempt_key' => 'riddle_id',
            'question' => 'question',
            'answer' => 'answer',
        ],
        Round::MODE_HERA => [
            'type' => RoundItem::PUZZLE_PROVERB,
            'model' => Proverb::class,
            'attempt' => ProverbAttempt::class,
            'attempt_key' => 'proverb_id',
            'question' => 'question',
            'answer' => 'answer',
        ],
        Round::MODE_TUJA => [
            'type' => RoundItem::PUZZLE_JOKE,
            'model' => Joke::class,
            'attempt' => JokeAttempt::class,
            'attempt_key' => 'joke_id',
            'question' => 'setup',
            'answer' => 'punchline',
        ],
    ];

    /**
     * Resolved round-level configuration.
     */
    public static function config(): array
    {
        return [
            'size' => (int) config('riddles.round_size', 10),
            'levels' => (int) config('riddles.round_levels', 5),
            'min_score' => (int) config('riddles.round_level_min_score', 8),
            'reveal_on_concede' => (bool) config('riddles.round_reveal_on_concede', true),
        ];
    }

    /**
     * Unsolved, active puzzles for a mode as tier-eligible { id, type, q, a } rows.
     */
    public static function source(string $mode, User $user): Collection
    {
        $map = static::MODE_MAP[$mode];

        $puzzles = $map['model']::query()
            ->where('is_suspended', false)
            ->orderBy('id')
            ->get();

        $solved = $map['attempt']::query()
            ->where('user_id', $user->id)
            ->where('is_correct', true)
            ->pluck($map['attempt_key'])
            ->all();

        return $puzzles
            ->reject(fn ($p) => in_array($p->id, $solved, true))
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => $map['type'],
                'q' => $p->{$map['question']},
                'a' => $p->{$map['answer']},
            ]);
    }

    /**
     * Build a tiered round pool for a level.
     *
     * @return array{pool: Collection<int, array{id: int, type: string, q: string, a: string}>, hasNext: bool, offset: int}
     */
    public static function buildPool(string $mode, int $level, User $user): array
    {
        $cfg = static::config();
        $source = static::source($mode, $user)->values()->all();

        if ($source === []) {
            return ['pool' => collect(), 'hasNext' => false, 'offset' => 0];
        }

        $tier = RinjoraTier::poolFor($source, $level, $cfg['size'], $cfg['levels']);

        return [
            'pool' => collect($tier['pool']),
            'hasNext' => $tier['hasNext'],
            'offset' => $tier['offset'],
        ];
    }

    /**
     * Whether a harder tier exists beyond the given level for a mode.
     *
     * Mirrors the prototype's `poolNiveau(...).hasNext`: it is true exactly
     * when the current level's window still has room to advance, i.e. a
     * higher tier produces a distinct, later pool.
     */
    public static function hasNextTier(string $mode, int $level, User $user): bool
    {
        return static::buildPool($mode, $level, $user)['hasNext'];
    }

    /**
     * Start a new round for a mode/level, finalizing any active one first.
     */
    public static function start(User $user, string $mode, int $level): ?Round
    {
        Round::query()
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->where('status', Round::STATUS_ACTIVE)
            ->update(['status' => Round::STATUS_COMPLETED, 'completed_at' => now()]);

        $pool = static::buildPool($mode, $level, $user)['pool'];

        if ($pool->isEmpty()) {
            return null;
        }

        $round = Round::create([
            'user_id' => $user->id,
            'mode' => $mode,
            'level' => $level,
            'item_count' => $pool->count(),
            'score' => 0,
            'current_streak' => 0,
            'best_streak' => 0,
            'status' => Round::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $pool->values()->each(function (array $item, int $position) use ($round) {
            RoundItem::create([
                'round_id' => $round->id,
                'puzzle_type' => $item['type'],
                'puzzle_id' => $item['id'],
                'position' => $position,
                'status' => RoundItem::STATUS_PENDING,
                'is_correct' => false,
                'attempts' => 0,
            ]);
        });

        return $round->refresh()->load('items');
    }

    /**
     * The current (first unfinished) item of a round, or null when completed.
     */
    public static function currentItem(Round $round): ?RoundItem
    {
        return $round->items->first(
            fn ($item) => $item->status === RoundItem::STATUS_PENDING
        );
    }

    /**
     * Whether the round has any unfinished items left.
     */
    public static function hasPendingItems(Round $round): bool
    {
        return static::currentItem($round) !== null;
    }

    /**
     * Mark the round completed and stamp it, computing the end-state summary.
     */
    public static function finalize(Round $round): Round
    {
        if (! $round->isCompleted()) {
            $round->update([
                'status' => Round::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return $round->refresh();
    }

    /**
     * Serialized round state for the client.
     */
    public static function roundPayload(Round $round, User $user): array
    {
        $cfg = static::config();

        $current = static::currentItem($round);
        $index = $current ? $current->position : $round->item_count;

        $hasMore = static::hasNextTier($round->mode, $round->level, $user);

        $nextLevel = $hasMore ? $round->level + 1 : null;
        $levelAvailable = $nextLevel !== null && $round->score >= $cfg['min_score'];

        return [
            'id' => $round->id,
            'mode' => $round->mode,
            'level' => $round->level,
            'item_count' => $round->item_count,
            'index' => $index,
            'score' => $round->score,
            'current_streak' => $round->current_streak,
            'best_streak' => $round->best_streak,
            'completed' => $round->isCompleted(),
            'has_more_levels' => $hasMore,
            'next_level' => $nextLevel,
            'level_available' => $levelAvailable,
        ];
    }

    /**
     * Game-facing payload for one round item (answers never exposed).
     */
    public static function itemPayload(RoundItem $item): array
    {
        $puzzle = $item->puzzleModel();

        if (! $puzzle) {
            return [
                'type' => $item->puzzle_type,
                'id' => $item->puzzle_id,
                'position' => $item->position,
                'question' => null,
            ];
        }

        $payload = [
            'type' => $item->puzzle_type,
            'id' => $puzzle->id,
            'position' => $item->position,
            'question' => $item->puzzle_type === RoundItem::PUZZLE_JOKE
                ? $puzzle->setup
                : $puzzle->question,
        ];

        if ($puzzle->category) {
            $payload['category'] = [
                'id' => $puzzle->category->id,
                'name' => $puzzle->category->name,
                'slug' => $puzzle->category->slug,
            ];
        }

        if ($item->puzzle_type !== RoundItem::PUZZLE_JOKE) {
            $payload['difficulty'] = $puzzle->difficulty;
        }

        if ($item->puzzle_type === RoundItem::PUZZLE_JOKE) {
            $payload['options'] = static::optionsFor($puzzle);
        }

        return $payload;
    }

    /**
     * The answer/punchline to reveal on a concede/skip, per game mode.
     */
    public static function revealedAnswer(string $mode, $puzzle): ?string
    {
        if (! $puzzle) {
            return null;
        }

        return $mode === Round::MODE_TUJA ? $puzzle->punchline : $puzzle->answer;
    }

    /**
     * The four displayed options for a joke: punchline + 3 distractors, shuffled.
     *
     * @return array<int, string>
     */
    public static function optionsFor(Joke $joke): array
    {
        $distractors = (array) ($joke->distractors ?? []);
        $options = array_merge([$joke->punchline], $distractors);

        $pad = Joke::query()
            ->where('is_suspended', false)
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
}
