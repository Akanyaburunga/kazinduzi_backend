<?php

namespace App\Support;

/**
 * Source dataset from the rinjora prototype (docs/rinjora.html).
 *
 * Reads docs/rinjora-data.json — the committed, human-auditable extraction of
 * the auto-extracted SOKWE / HERAHEZA / TUJAJURE arrays — so the full
 * collections are available to seeders and to the round-tiering logic without
 * duplicating data. Falls back to the legacy PHP data file if the JSON is
 * missing so existing deployments keep working.
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
            // Resolved without app() since this class is used by plain-PHPUnit
            // unit tests (RinjoraDataTest) that boot no Laravel container.
            $jsonPath = dirname(__DIR__, 2).'/docs/rinjora-data.json';
            if (is_file($jsonPath)) {
                $decoded = json_decode((string) file_get_contents($jsonPath), true);
                static::$data = is_array($decoded)
                    ? $decoded
                    : require __DIR__.'/data/rinjora.php';
            } else {
                static::$data = require __DIR__.'/data/rinjora.php';
            }
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
