<?php

namespace App\Support;

use App\Models\Challenge;
use App\Models\ReputationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Duels
{
    /**
     * Resolve an accepted duel to its completed state, transferring the wager
     * to the winner. The faster correct solve wins; if both players solved at
     * the exact same instant, or neither solved, no wager changes hands.
     */
    public static function resolve(Challenge $challenge): Challenge
    {
        return DB::transaction(function () use ($challenge) {
            $correct = $challenge->attempts()
                ->where('is_correct', true)
                ->orderBy('created_at')
                ->get();

            $winner = null;

            if ($correct->count() === 1) {
                $winner = $correct->first()->user;
            } elseif ($correct->count() === 2) {
                $first = $correct->first();
                if (! $first->created_at->equalTo($correct->last()->created_at)) {
                    $winner = $first->user;
                }
            }

            if ($winner) {
                $challenge->winner_id = $winner->id;
                self::transferWager($challenge, $winner);
            }

            $challenge->status = Challenge::STATUS_COMPLETED;
            $challenge->resolved_at = now();
            $challenge->save();

            return $challenge;
        });
    }

    /**
     * Resolve a stale accepted duel where one side never finished: pick the one
     * player who solved as the winner, otherwise void the wager.
     */
    public static function settle(Challenge $challenge): Challenge
    {
        return DB::transaction(function () use ($challenge) {
            $solved = $challenge->attempts()
                ->where('is_correct', true)
                ->orderBy('created_at')
                ->get();

            $winner = $solved->isNotEmpty() ? $solved->first()->user : null;

            if ($winner) {
                $challenge->winner_id = $winner->id;
                self::transferWager($challenge, $winner);
            }

            $challenge->status = Challenge::STATUS_COMPLETED;
            $challenge->resolved_at = now();
            $challenge->save();

            return $challenge;
        });
    }

    /**
     * Move the wager from loser to winner, honouring the daily solve reputation
     * cap (so a duel cannot farm past it) and never driving a reputation below
     * zero. The gain is clamped by the winner's remaining headroom for the day,
     * and the loser forfeits exactly that clamped amount to keep the books even.
     */
    private static function transferWager(Challenge $challenge, User $winner): void
    {
        $wager = (int) $challenge->wager;
        if ($wager <= 0) {
            return;
        }

        $loser = $challenge->initiator_id === $winner->id
            ? $challenge->opponent
            : $challenge->initiator;

        $cap = (int) config('riddles.daily_solve_reputation_cap');
        $earnedToday = (int) ReputationLog::where('user_id', $winner->id)
            ->whereDate('created_at', now()->toDateString())
            ->sum('change');

        $remaining = $cap > 0 ? max(0, $cap - $earnedToday) : $wager;
        $gain = $cap > 0 ? min($wager, $remaining) : $wager;

        if ($gain <= 0) {
            return;
        }

        $winner->updateReputation($gain, "Won a duel vs {$loser->name}", $challenge);

        $loserForfeit = min($gain, max(0, (int) $loser->reputation));
        if ($loserForfeit > 0) {
            $loser->updateReputation(-$loserForfeit, "Lost a duel vs {$winner->name}", $challenge);
        }
    }
}
