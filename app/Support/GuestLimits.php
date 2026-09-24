<?php

namespace App\Support;

use App\Models\GuestPlay;
use App\Models\Round;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Admin-configurable guest play limits.
 *
 * Each game mode (sokwe / hera / tuja) has a "free" play allowance a guest
 * may use without an account — covering both round-mode plays (one Round row
 * per round started) and the classic single-play endpoints (one GuestPlay row
 * per distinct puzzle answered). The value is stored in the settings table
 * (overrideable per mode) and falls back to config/riddles defaults.
 */
class GuestLimits
{
    public const MODES = [Round::MODE_SOKWE, Round::MODE_HERA, Round::MODE_TUJA];

    public const KEY_PREFIX = 'guest_round_limit';

    public static function key(string $mode): string
    {
        return self::KEY_PREFIX.'.'.$mode;
    }

    /**
     * Config default for a mode.
     */
    public static function default(string $mode): int
    {
        return (int) config('riddles.guest_round_limits.'.$mode, 0);
    }

    /**
     * Effective limit for a mode (settings override, else default).
     */
    public static function limit(string $mode): int
    {
        $stored = Setting::get(self::key($mode));

        if ($stored === null || trim((string) $stored) === '') {
            return self::default($mode);
        }

        return max(0, (int) $stored);
    }

    /**
     * Number of plays a user has used up in a mode. Round-mode plays count
     * every round started (even abandoned ones); classic plays count every
     * distinct puzzle answered on the legacy endpoints. Both draw from the
     * same per-mode allowance.
     */
    public static function used(string $mode, User $user): int
    {
        return Round::query()
            ->where('user_id', $user->id)
            ->where('mode', $mode)
            ->count()
            + GuestPlay::query()
                ->where('user_id', $user->id)
                ->where('mode', $mode)
                ->count();
    }

    /**
     * Record that a guest answered a puzzle on a classic (legacy) endpoint.
     * Records at most one row per puzzle per mode, so re-answering the same
     * puzzle never consumes additional allowance.
     */
    public static function recordLegacyPlay(string $mode, User $user, string $puzzleType, int $puzzleId): void
    {
        GuestPlay::query()->firstOrCreate([
            'user_id' => $user->id,
            'mode' => $mode,
            'puzzle_type' => $puzzleType,
            'puzzle_id' => $puzzleId,
        ]);
    }

    public static function remaining(string $mode, User $user): int
    {
        return max(0, self::limit($mode) - self::used($mode, $user));
    }

    public static function requiresRegistration(string $mode, User $user): bool
    {
        return self::remaining($mode, $user) <= 0;
    }

    /**
     * Per-mode guest status payload.
     */
    public static function status(string $mode, User $user): array
    {
        return [
            'mode' => $mode,
            'limit' => self::limit($mode),
            'used' => self::used($mode, $user),
            'remaining' => self::remaining($mode, $user),
            'requires_registration' => self::requiresRegistration($mode, $user),
        ];
    }

    /**
     * Standard 403 "create an account" response used by every guest-facing
     * play endpoint (rounds and classic games) once a mode's allowance is
     * exhausted.
     */
    public static function blockedResponse(string $mode, User $user): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You have used all your free plays for this game. Create an account to keep playing.',
            'requires_registration' => true,
            'guest' => self::status($mode, $user),
        ], 403);
    }

    /**
     * Guest status across all modes.
     */
    public static function summary(User $user): array
    {
        return array_map(fn (string $mode) => self::status($mode, $user), self::MODES);
    }

    public static function setLimit(string $mode, int $limit): void
    {
        Setting::set(self::key($mode), (string) max(0, $limit));
    }

    public static function clearLimit(string $mode): void
    {
        Setting::forget(self::key($mode));
    }

    public static function hasOverride(string $mode): bool
    {
        return Setting::get(self::key($mode)) !== null;
    }
}