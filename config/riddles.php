<?php

return [
    /*
    | New users start with this many streak saver freezes available.
    */
    'streak_freezes' => (int) env('STREAK_FREEZE_LIMIT', 3),

    /*
    | Maximum reputation a user can earn from solving riddles in a single day.
    | Prevents reputation farming by capping daily solve rewards.
    */
    'daily_solve_reputation_cap' => (int) env('DAILY_SOLVE_REPUTATION_CAP', 50),

    /*
    | Reputation awarded for each first correct solve of a riddle.
    */
    'solve_reputation' => (int) env('RIDDLE_SOLVE_REPUTATION', 5),

    /*
    | Maximum reputation stake allowed on a single duel wager.
    */
    'duel_max_wager' => (int) env('DUEL_MAX_WAGER', 20),

    /*
    | Hours a pending duel stays open for acceptance before it auto-expires.
    */
    'duel_stale_hours' => (int) env('DUEL_STALE_HOURS', 24),
];
