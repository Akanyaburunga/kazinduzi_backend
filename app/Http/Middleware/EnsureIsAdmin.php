<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsAdmin
{
    /**
     * Ensure the authenticated session user is an admin, i.e. their
     * reputation meets the moderation threshold.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || auth()->user()->reputation < env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
