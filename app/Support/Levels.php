<?php

namespace App\Support;

/**
 * Simple reputation-derived level system.
 *
 * Levels are computed (no schema change); total reputation on the User remains
 * the single source of points. Thresholds can be tuned via config.
 */
class Levels
{
    /**
     * Level thresholds keyed by level => minimum reputation required.
     * Level 1 requires no reputation.
     */
    public const THRESHOLDS = [
        1 => 0,
        2 => 50,
        3 => 150,
        4 => 350,
        5 => 700,
        6 => 1200,
        7 => 1900,
        8 => 2800,
        9 => 4000,
        10 => 5500,
    ];

    /**
     * Human-readable Kirundi titles per level.
     */
    public const TITLES = [
        1 => 'Umuto',
        2 => 'Umutoi',
        3 => 'Umukinnyi',
        4 => 'Umwarimu',
        5 => 'Umuhuzabikorwa',
        6 => 'Umukoresha',
        7 => 'Inzobere',
        8 => 'Umushishozi',
        9 => 'Inyenyeri',
        10 => 'Umutagatifu',
    ];

    public static function levelForReputation(int $reputation): int
    {
        $level = 1;
        foreach (static::THRESHOLDS as $thresholdLevel => $minimum) {
            if ($reputation >= $minimum) {
                $level = $thresholdLevel;
            }
        }

        return $level;
    }

    public static function titleForLevel(int $level): string
    {
        return static::TITLES[$level] ?? 'Umuto';
    }

    public static function currentTitle(int $reputation): string
    {
        return static::titleForLevel(static::levelForReputation($reputation));
    }

    public static function currentLevel(int $reputation): array
    {
        $level = static::levelForReputation($reputation);
        $currentMin = static::THRESHOLDS[$level];

        $nextLevel = null;
        $progress = 100.0;
        foreach (static::THRESHOLDS as $thresholdLevel => $minimum) {
            if ($minimum > $currentMin) {
                $nextLevel = $thresholdLevel;
                $nextMin = $minimum;
                $span = $nextMin - $currentMin;
                $progress = $span > 0
                    ? round((($reputation - $currentMin) / $span) * 100, 1)
                    : 100.0;
                break;
            }
        }

        if ($nextLevel === null) {
            $progress = 100.0;
        }

        return [
            'level' => $level,
            'title' => static::titleForLevel($level),
            'current_min' => $currentMin,
            'next_level' => $nextLevel,
            'next_min' => $nextLevel !== null ? static::THRESHOLDS[$nextLevel] : null,
            'progress_to_next' => $progress,
        ];
    }
}
