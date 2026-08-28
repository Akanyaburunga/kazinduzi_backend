<?php

return [
    /*
    | New users start with this many streak saver freezes available.
    */
    'streak_freezes' => (int) env('STREAK_FREEZE_LIMIT', 3),
];
