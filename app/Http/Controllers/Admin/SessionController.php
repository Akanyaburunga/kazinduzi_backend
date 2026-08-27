<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Return the current authenticated admin session state. Guarded by the
     * `admin` middleware, so reaching this always means the user is an admin.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => true,
            'admin' => true,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'reputation' => $user->reputation,
            ] : null,
        ]);
    }
}
