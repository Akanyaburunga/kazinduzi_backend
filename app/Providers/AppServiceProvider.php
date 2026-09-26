<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Guest session mint: generous budget keyed by the device-held
        // guest_uid (guessing it equals brute-forcing the ANDROID_ID), so
        // crash loops / 401-recovery re-mints never lock the device out.
        RateLimiter::for('guest-session', function (Request $request) {
            $key = $request->input('guest_uid') ?: ($request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('riddles.guest_session_throttle', 60))
                ->by('guest-session:'.$key);
        });

        // Flood ceiling keyed by client IP so one source cannot mint an
        // unbounded number of guest rows even with rotating guest_uids.
        RateLimiter::for('guest-session-ip', function (Request $request) {
            return Limit::perMinute((int) config('riddles.guest_session_ip_throttle', 600))
                ->by('guest-session-ip:'.($request->ip() ?? 'unknown'));
        });
    }
}
