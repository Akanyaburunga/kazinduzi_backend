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

    // Fetch users with correctly computed total reputation, total words, and total meanings
    $users = User::select('users.id', 'users.name', 'users.reputation')
        // Get total reputation using a subquery
        ->selectSub(function ($query) use ($startDate, $endDate) {
            $query->from('reputation_logs')
                ->whereColumn('reputation_logs.user_id', 'users.id')
                ->whereBetween('reputation_logs.created_at', [$startDate, $endDate])
                ->selectRaw('COALESCE(SUM(reputation_logs.change), 0)');
        }, 'total_reputation')

        // Get total words contributed by the user
        ->selectSub(function ($query) {
            $query->from('words')
                ->whereColumn('words.user_id', 'users.id')
                ->selectRaw('COUNT(*)');
        }, 'total_words')

        // Get total meanings contributed by the user
        ->selectSub(function ($query) {
            $query->from('meanings')
                ->whereColumn('meanings.user_id', 'users.id')
                ->selectRaw('COUNT(*)');
        }, 'total_meanings')

        ->havingRaw('total_reputation > 0') // Exclude users with zero or negative reputation
        ->orderByDesc('total_reputation')
        ->paginate(10);

    return view('leaderboard.index', compact('users', 'filter'));

    }

}