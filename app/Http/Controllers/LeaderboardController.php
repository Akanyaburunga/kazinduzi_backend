<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request, $filter = 'all-time')
    {

    // Default to 'all_time' if no filter is provided
    $filter = $request->input('filter', 'all_time');

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

    // Fetch users with total reputation, total words, and total meanings contributed
    $users = User::leftJoin('reputation_logs', 'users.id', '=', 'reputation_logs.user_id')
        ->leftJoin('words', 'users.id', '=', 'words.user_id') // Join to count words
        ->leftJoin('meanings', 'users.id', '=', 'meanings.user_id') // Join to count meanings
        ->select('users.id', 'users.name', 'users.email', 'users.reputation',
            DB::raw('COALESCE(SUM(reputation_logs.change), 0) AS total_reputation'),
            DB::raw('COUNT(DISTINCT words.id) AS total_words'),
            DB::raw('COUNT(DISTINCT meanings.id) AS total_meanings')
        )
        ->whereBetween('reputation_logs.created_at', [$startDate, $endDate]) // Apply the date filter
        ->groupBy('users.id', 'users.name', 'users.email', 'users.reputation')
        ->orderByDesc('total_reputation')
        ->paginate(10);

    return view('leaderboard.index', compact('users', 'filter'));

    }

}