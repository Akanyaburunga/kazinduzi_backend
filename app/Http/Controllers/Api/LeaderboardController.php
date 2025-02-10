<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

class LeaderboardController extends Controller
{
    /**
     * Display the leaderboard, ranked by total reputation.
     */
    public function index(Request $request)
    {
        // Query to get users with reputation > 0, sorted by total reputation
        $leaderboard = User::where('reputation', '>', 0) // Exclude users with reputation <= 0
            ->orderByDesc('reputation') // Sort by reputation in descending order
            ->take(10) // Limit to top 10 users
            ->get(['id', 'name', 'reputation']);

        return response()->json([
            'success' => true,
            'leaderboard' => $leaderboard,
        ]);
    }
}