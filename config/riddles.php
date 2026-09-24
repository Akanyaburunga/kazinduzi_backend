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

    /*
    | Number of items served per round (mirrors the rinjora prototype's
    | ROUND_SIZE = 10).
    */
    'round_size' => (int) env('ROUND_SIZE', 10),

    /*
    | Minimum round score (out of round_size) required before the player is
    | offered a harder (next) tier level.
    */
    'round_level_min_score' => (int) env('ROUND_LEVEL_MIN_SCORE', 8),

    /*
    | Number of distinct difficulty tiers used to build round pools.
    */
    'round_levels' => (int) env('ROUND_LEVELS', 5),

    /*
    | Whether revealing the answer on a conceded/skipped round item is allowed.
    */
    'round_reveal_on_concede' => (bool) env('ROUND_REVEAL_ON_CONCEDE', true),

    /*
    | Randomize the order of the items selected for each round.
    |
    | Each player's round is shuffled with a seed derived from their id, the
    | game mode, the level and the day, so two players get a different order
    | while replays on the same day stay stable (and tests stay deterministic
    | when Carbon time is frozen).
    */
    'round_shuffle_order' => (bool) env('ROUND_SHUFFLE_ORDER', true),

    /*
    | How many of the player's most recent rounds are remembered so that items
    | just delivered to them are skipped on the next round whenever enough
    | fresh content remains.
    */
    'round_recency_rounds' => (int) env('ROUND_RECENCY_ROUNDS', 3),

    /*
    | Prevent the same punchline from being offered both as the correct option
    | of one joke and as a distractor of another joke inside the same round.
    */
    'round_dedupe_distractors' => (bool) env('ROUND_DEDUPE_DISTRACTORS', true),

    /*
    | Lenient answer-matching behaviour (App\Support\AnswerMatcher).
    */
    'answer_match' => [
        /*
        | Accept a single matching content word (free order / partial).
        | Mirrors the rinjora prototype's lenient matching.
        */
        'allow_partial' => (bool) env('ANSWER_MATCH_ALLOW_PARTIAL', true),

        /*
        | Minimum token length considered a "content" word for partial/free
        | order matching.
        */
        'min_partial_word' => 3,

        /*
        | Words ignored when re-ordering / matching partial answers.
        */
        'stop_words' => ['na', 'n', 'mu', 'ku', 'i', 'a', 'ya', 'wa', 'y', 'w'],
    ],
];
