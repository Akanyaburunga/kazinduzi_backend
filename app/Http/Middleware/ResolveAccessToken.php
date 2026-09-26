<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restore bearer auth on PHP-FPM hosts where the Authorization header does
 * not cross into the process (cPanel: CGIPassAuth off). Natively, the client
 * sends Authorization: Bearer <token>; when a WAF/FPM layer drops that header,
 * the client may carry the same token in X-Access-Token instead. This bridge
 * promotes it to the standard header before any auth middleware runs.
 */
class ResolveAccessToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->headers->has('authorization')
            && $request->headers->has('x-access-token')) {
            $request->headers->set('Authorization', 'Bearer '.$request->header('x-access-token'));
        }

        return $next($request);
    }
}