<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerifiedOrGuest
{
    /**
     * Allow guests (no-account players) through while requiring the normal
     * verified-email check for registered users.
     */
    public function handle(Request $request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isGuest') && $user->isGuest()) {
            return $next($request);
        }

        return (new EnsureEmailIsVerifiedApi())->handle($request, $next, $redirectToRoute);
    }
}