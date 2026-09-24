<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Return the current authenticated admin session state. Guarded by the
     * `admin` middleware, so reaching this always means the user is an admin.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->sessionPayload($user));
    }

    /**
     * Authenticate a user via the web session for the admin panel and return
     * the established session state.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->is_banned) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Your account has been banned from the platform.'], 403);
        }

        if ($user->reputation < (int) env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'You do not have moderator access to this panel.'], 403);
        }

        return response()->json($this->sessionPayload($user));
    }

    /**
     * Destroy the authenticated admin web session.
     */
    public function destroy(Request $request): JsonResponse
    {
        auth()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function sessionPayload(?object $user): array
    {
        return [
            'authenticated' => (bool) $user,
            'admin' => (bool) $user,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'reputation' => $user->reputation,
            ] : null,
        ];
    }
}
