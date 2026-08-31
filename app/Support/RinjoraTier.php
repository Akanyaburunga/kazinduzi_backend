<?php

namespace App\Support;

/**
 * Difficulty tiers + round-pool selection, mirroring the rinjora prototype's
 * `difficulte()` and `poolNiveau()` helpers (docs/rinjora.html).
 *
 * Items are plain arrays, either { q, a } (riddle/proverb) or { t, p } (joke).
 * Difficulty is estimated as `answer_length * 2 + prompt_length`, then items
 * are sorted ascending and a level slices out the next window of up to
 * round_size items.
 */
class RinjoraTier
{
    /**
     * Estimated difficulty of one item, per the prototype.
     */
    public static function difficulte(array $item): int
    {
        $prompt = $item['q'] ?? $item['t'] ?? '';
        $answer = $item['a'] ?? $item['p'] ?? '';

        return (mb_strlen($answer) * 2) + mb_strlen($prompt);
    }

    /**
     * Build a round pool (unsolved items preserved in order) for a level.
     *
     * @param  array  $source      ordered items, each { q|t, a|p }
     * @param  int    $level       1-based difficulty tier
     * @param  int    $roundSize   items per round (ROUND_SIZE = 10)
     * @return array{pool: array, hasNext: bool, offset: int}
     */
    public static function poolFor(array $source, int $level, int $roundSize = 10, int $levels = 5): array
    {
        if ($level < 1) {
            $level = 1;
        }

        usort($source, static fn ($a, $b) => static::difficulte($a) <=> static::difficulte($b));

        $n = count($source);
        $pas = max($roundSize, (int) floor($n / $levels));
        $maxStart = max(0, $n - $roundSize);
        $debut = min(($level - 1) * $pas, $maxStart);
        $part = array_slice($source, $debut);

        return [
            'pool' => array_slice($part, 0, min($roundSize, count($part))),
            'hasNext' => $debut < ($n - $roundSize),
            'offset' => $debut,
        ];
    }

    /**
     * Whether a score (out of roundSize) is enough to offer a harder tier.
     */
    public static function qualifiesForNext(int $score, int $roundSize, int $minScore): bool
    {
        return $score >= min($minScore, $roundSize);
    }
}
