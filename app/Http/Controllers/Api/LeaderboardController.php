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
    public function index(Request $request, $filter = 'all-time')
{
    // Default to 'this_week' if no filter is provided
    $filter = $request->input('filter', 'this_week');

    // Define the start and end dates for each filter
    switch ($filter) {
        case 'today':
            $startDate = Carbon::today();
            $endDate = Carbon::today()->endOfDay();
            break;
        case 'this_week':
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now()->endOfWeek();
            break;
        case 'this_month':
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            break;
        case 'this_year':
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
            break;
        case 'all_time':
        default:
            $startDate = Carbon::minValue(); // Very old date
            $endDate = Carbon::now();
            break;
    }

    // Fetch users with their total reputation, total words, and total meanings
    $users = User::select('users.id', 'users.name', 'users.reputation')
        // Get total reputation using a subquery
        ->addSelect([
            'total_reputation' => User::selectRaw('COALESCE(SUM(reputation_logs.change), 0)')
                ->from('reputation_logs')
                ->whereColumn('reputation_logs.user_id', 'users.id')
                ->whereBetween('reputation_logs.created_at', [$startDate, $endDate])
        ])
        // Get total words contributed by the user
        ->addSelect([
            'total_words' => User::selectRaw('COUNT(*)')
                ->from('words')
                ->whereColumn('words.user_id', 'users.id')
        ])
        // Get total meanings contributed by the user
        ->addSelect([
            'total_meanings' => User::selectRaw('COUNT(*)')
                ->from('meanings')
                ->whereColumn('meanings.user_id', 'users.id')
        ])
        ->havingRaw('total_reputation > 0') // Exclude users with zero or negative reputation
        ->orderByDesc('total_reputation')
        ->take(10) // Limit to top 10 users
            ->get(['id', 'name', 'total_reputation']);; // Order by total reputation in descending order

    // Prepare the response with pagination metadata and the user data
    return response()->json([
        $users
    ]);
}
}