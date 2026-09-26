<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY diagnostic endpoint — diagnose the prod-only 401 on guest/game
 * routes. Gated by ?key= that must equal the app's APP_KEY. DELETE this
 * controller and its route once the investigation is complete.
 */
class ServerDebugController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! hash_equals(
            hash('sha256', (string) config('app.key')),
            hash('sha256', (string) $request->query('key'))
        )) {
            abort(403, 'forbidden');
        }

        $conn = DB::connection();

        $tokenId = $request->query('token_id');
        $plain = $request->query('plain');

        $tokenInfo = null;
        if ($tokenId) {
            $row = $conn->table('personal_access_tokens')->where('id', (int) $tokenId)->first();
            $tokenInfo = $row ? [
                'row_exists' => true,
                'id' => (int) $row->id,
                'tokenable_type' => $row->tokenable_type,
                'tokenable_id' => (int) $row->tokenable_id,
                'name' => $row->name,
                'created_at' => $row->created_at,
                'expires_at' => $row->expires_at,
                'last_used_at' => $row->last_used_at,
                'user_row_exists' => $conn->table('users')->where('id', (int) $row->tokenable_id)->exists(),
                'provider_model_matches' => $row->tokenable_type === config('auth.providers.users.model'),
                'user_db' => $conn->table('users')->getConnection()->getDatabaseName(),
                'token_db' => $conn->getDatabaseName(),
                'plain_hash_matches' => $plain ? hash_equals($row->token, hash('sha256', (string) $plain)) : null,
            ] : ['row_exists' => false];
        }

        return response()->json([
            'ok' => true,
            'note' => 'TEMPORARY debug endpoint — remove after diagnosis (file ServerDebugController.php + /api/_debug/server route).',
            'request' => [
                'php_sapi_name' => php_sapi_name(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                'http_authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
                'redirect_http_authorization' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
                'temp_auth_ok' => $_SERVER['TEMP_AUTH_OK'] ?? null,
                'php_auth_user_set' => ! empty($_SERVER['PHP_AUTH_USER']),
                'php_auth_pw_set' => ! empty($_SERVER['PHP_AUTH_PW']),
                'headers_as_seen_by_laravel' => $request->headers->all(),
            ],
            'app' => [
                'env' => config('app.env'),
                'url' => config('app.url'),
                'config_cached' => file_exists(base_path('bootstrap/cache/config.php')),
                'route_cached' => glob(base_path('bootstrap/cache/routes-*.php')) !== [],
                'app_key_sha256' => substr(hash('sha256', (string) config('app.key')), 0, 8).'…',
            ],
            'auth' => [
                'provider_model' => config('auth.providers.users.model'),
                'sanctum_guard' => config('sanctum.guard'),
                'sanctum_expiration' => config('sanctum.expiration'),
                'stateful_domains' => config('sanctum.stateful'),
                'token_expiry_env_direct_read' => env('SANCTUM_TOKEN_EXPIRY'),
            ],
            'db' => [
                'connection' => $conn->getName(),
                'database' => $conn->getDatabaseName(),
            ],
            'token_probe' => $tokenInfo,
        ]);
    }
}