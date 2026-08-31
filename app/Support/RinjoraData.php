<?php

namespace App\Support;

/**
 * Source dataset from the rinjora prototype (docs/rinjora.html).
 *
 * Reads the auto-extracted arrays in app/Support/data/rinjora.php so the
 * full SOKWE / HERAHEZA / TUJAJURE collections are available to seeders and
 * to the round-tiering logic without duplicating data.
 */
class RinjoraData
{
    /**
     * The loaded dataset, memoized.
     *
     * @var array{sokwe: array, heraheza: array, tujajure: array}|null
     */
    protected static ?array $data = null;

    /**
     * Full extracted dataset.
     */
    public static function all(): array
    {
        if (static::$data === null) {
            static::$data = require __DIR__.'/data/rinjora.php';
        }

        return static::$data;
    }

    /**
     * 216 SOKWE riddles as { q, a } rows. `a` may contain `/` alternatives.
     */
    public static function sokwe(): array
    {
        return static::all()['sokwe'];
    }

    /**
     * 162 HERAHEZA proverbs as { q, a } rows (q ends with an ellipsis).
     */
    public static function heraheza(): array
    {
        return static::all()['heraheza'];
    }

    /**
     * 16 TUJAJURE jokes as { t, p } rows.
     */
    public static function tujajure(): array
    {
        return static::all()['tujajure'];
    }
}
