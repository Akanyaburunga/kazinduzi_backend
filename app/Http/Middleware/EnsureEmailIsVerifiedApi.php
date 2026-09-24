<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;

class EnsureEmailIsVerifiedApi
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $redirectToRoute = null)
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
            ! $request->user()->hasVerifiedEmail())) {
            $api = $request->is('api/*');

            if ($request->expectsJson() || $api) {
                $guest = $request->user() && method_exists($request->user(), 'isGuest')
                    && $request->user()->isGuest();

                return response()->json([
                    'success' => false,
                    'message' => $guest ? 'Create an account to continue.' : 'Your email address is not verified.',
                    'requires_registration' => $guest,
                ], 403);
            }

            return Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
