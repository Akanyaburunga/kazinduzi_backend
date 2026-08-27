<?php

namespace App\Http\Controllers\Api\Riddle;

trait IsCurator
{
    /**
     * Abort unless the authenticated user's reputation is at or above the
     * moderation threshold. This mirrors the existing moderation gating.
     */
    protected function authorizeCurator()
    {
        $threshold = (int) env('MODERATION_REPUTATION_THRESHOLD', 500);

        abort_unless(
            auth()->check() && auth()->user()->reputation >= $threshold,
            403,
            'You are not authorized to perform this action.'
        );
    }
}
