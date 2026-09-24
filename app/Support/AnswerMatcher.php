<?php

namespace App\Support;

/**
 * Lenient, Kirundi-aware answer matching.
 *
 * Mirrors the intent of the rinjora.html prototype: accepts free word order,
 * `/`-separated alternatives, typos, shared radicals and suffixes, and (by
 * default) a single matching content word. Purely deterministic — no DB.
 */
class AnswerMatcher
{
    /**
     * Whether a submitted answer matches any alternative of the stored answer.
     *
     * @param  array  $options
     */
    public static function isCorrect(string $guess, string $answer, array $options = []): bool
    {
        $normalized = static::normalize($guess);
        if ($normalized === '') {
            return false;
        }

        foreach (static::alternatives($answer) as $candidate) {
            if ($candidate === '' || $candidate === null) {
                continue;
            }
            if (static::matches($guess, $candidate, $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the guess is the "give up" gesture (ndaguhaye).
     */
    public static function isConcede(string $guess): bool
    {
        return static::normalize($guess) === 'ndaguhaye';
    }

    /**
     * Normalize for *display/consistency* and for the primary comparisons:
     * lowercase, strip combining marks, map smart quotes to spaces, collapse
     * punctuation, collapse whitespace.
     */
    public static function normalize(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value);
        } else {
            $value = strtolower($value);
        }

        // NFD + strip all combining marks (accents, cedillas, ...).
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_D);
        }
        $value = preg_replace('/\p{M}/u', '', $value);

        // Typographic / ASCII apostrophes become word separators.
        $value = str_replace(["'", "`", "´", "\u{2018}", "\u{2019}", "\u{201B}"], ' ', $value);
        // Punctuation becomes whitespace.
        $value = preg_replace('/[.,!?;:()"…]/u', ' ', $value);
        // Collapse whitespace.
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Split a stored answer on `/` into trimmed alternatives.
     *
     * @return array<int, string>
     */
    public static function alternatives(string $answer): array
    {
        return array_map('trim', explode('/', $answer));
    }

    /**
     * Tokenize a normalized string into words.
     *
     * @return array<int, string>
     */
    protected static function tokens(string $normalized): array
    {
        return array_values(array_filter(preg_split('/\s+/', $normalized)));
    }

    /**
     * "Content" tokens: drop stop-words and short tokens (used for free-order
     * and partial matching).
     *
     * @return array<int, string>
     */
    protected static function contentTokens(string $normalized, array $options): array
    {
        $stopWords = $options['stopWords'] ?? ['na', 'n', 'mu', 'ku', 'i', 'a', 'ya', 'wa', 'y', 'w'];
        $min = (int) ($options['minPartialWord'] ?? 3);

        return array_values(array_filter(
            static::tokens($normalized),
            function (string $token) use ($stopWords, $min): bool {
                return ! in_array($token, $stopWords, true)
                    && mb_strlen($token) >= $min;
            }
        ));
    }

    /**
     * Normalized tokens re-ordered (for free-word-order comparison).
     */
    protected static function reordered(string $normalized, array $options): string
    {
        $tokens = static::contentTokens($normalized, $options);
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Levenshtein distance (byte-safe; inputs are already normalized/ASCII).
     */
    protected static function levenshtein(string $a, string $b): int
    {
        return levenshtein($a, $b);
    }

    /**
     * Typo tolerance based on the longer string's length.
     */
    protected static function tolerance(string $a, string $b): int
    {
        $len = max(mb_strlen($a), mb_strlen($b));

        return $len <= 4 ? 0 : ($len <= 7 ? 1 : 2);
    }

    /**
     * Whether two tokens are "close": exact, typo-tolerant, shared radical, or
     * a common suffix (handles uruyuki / akayuki class prefixes).
     */
    protected static function tokensClose(string $g, string $a): bool
    {
        if ($g === $a) {
            return true;
        }

        if (static::levenshtein($g, $a) <= static::tolerance($g, $a)) {
            return true;
        }

        if (mb_strlen($g) >= 4 && (str_contains($a, $g) || str_contains($g, $a))) {
            return true;
        }

        return static::commonSuffix($g, $a) >= 4;
    }

    /**
     * Length of the common suffix shared by two strings.
     */
    protected static function commonSuffix(string $a, string $b): int
    {
        $k = 0;
        $i = mb_strlen($a) - 1;
        $j = mb_strlen($b) - 1;

        while ($i >= 0 && $j >= 0 && mb_substr($a, $i, 1) === mb_substr($b, $j, 1)) {
            $k++;
            $i--;
            $j--;
        }

        return $k;
    }

    /**
     * Core matching used against a single candidate answer.
     */
    protected static function matches(string $guess, string $candidate, array $options): bool
    {
        $g = static::normalize($guess);
        $r = static::normalize($candidate);

        if ($g === $r) {
            return true;
        }

        // Free word order (ignoring stop-words / short tokens).
        if (static::reordered($g, $options) !== '' && static::reordered($g, $options) === static::reordered($r, $options)) {
            return true;
        }

        // Typo tolerance on the whole phrase.
        if (static::levenshtein($g, $r) <= static::tolerance($g, $r)) {
            return true;
        }

        $allowPartial = (bool) ($options['allowPartial'] ?? true);
        if ($allowPartial) {
            $gw = static::contentTokens($g, $options);
            $aw = static::contentTokens($r, $options);

            if ($gw === [] || $aw === []) {
                return false;
            }

            foreach ($gw as $gt) {
                foreach ($aw as $at) {
                    if (static::tokensClose($gt, $at)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
