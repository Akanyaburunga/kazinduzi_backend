<?php

namespace App\Http\Controllers\Api;

use App\Models\ReputationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class LeaderboardController extends Controller
{
    public const FILTERS = ['today', 'this_week', 'this_month', 'this_year', 'all_time'];

    /**
     * Leaderboard ranked by reputation earned within the selected period,
     * including the authenticated user's own rank ("where am I").
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $filter = $request->input('filter', 'all_time');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all_time';
        }

        [$start, $end] = $this->dateRange($filter);

        // Per-user summed points within the period (net positive only).
        $aggregate = ReputationLog::query()
            ->select('user_id')
            ->selectRaw('SUM(change) as points')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('user_id')
            ->havingRaw('SUM(change) > 0')
            ->orderByDesc(DB::raw('SUM(change)'))
            ->orderBy('user_id')
            ->get();

        $totalPlayers = $aggregate->count();

        // Paginate the ranked list.
        $perPage = max(1, $request->integer('per_page', 20));
        $page = max(1, $request->integer('page', 1));
        $items = $aggregate->forPage($page, $perPage)->values();

        $userIds = $items->pluck('user_id')->all();
        $usersById = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Assign ranks (aggregate is already ordered desc by points).
        $ranked = [];
        foreach ($items as $item) {
            $u = $usersById->get($item->user_id);
            if (! $u) {
                continue;
            }
            $ranked[] = [
                'rank' => $this->rankOf($aggregate, $item->points) + 1,
                'id' => $u->id,
                'name' => $u->name,
                'points' => (int) $item->points,
                'words_contributed' => $u->words()->count(),
                'meanings_contributed' => $u->meanings()->count(),
                'profile_picture_url' => $u->profile_picture
                    ? asset('storage/' . $u->profile_picture)
                    : asset('images/default-profile.png'),
            ];
        }

        // "Where am I" for the authenticated user (ranked even outside the page).
        $myPoints = (int) ReputationLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum('change');

        $betterThan = $aggregate->filter(fn ($r) => $r->points > $myPoints)->count();
        $myRank = $betterThan + 1;
        $percentile = $totalPlayers > 0
            ? (int) round(max(0, (($totalPlayers - $myRank + 1) / $totalPlayers) * 100))
            : 0;

        $lastPage = (int) ceil($totalPlayers / $perPage);

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'data' => $ranked,
            'me' => [
                'id' => $user->id,
                'name' => $user->name,
                'rank' => $myRank,
                'points' => $myPoints,
                'total_players' => $totalPlayers,
                'percentile' => $percentile,
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalPlayers,
                'last_page' => $lastPage === 0 ? 1 : $lastPage,
            ],
        ]);
    }

    /**
     * 0-based position of the first entry with at least the given points,
     * plus the count of ties, so equal scores share the top bound rank.
     */
    protected function rankOf($aggregate, int $points): int
    {
        $rank = 0;
        foreach ($aggregate as $row) {
            if ($row->points > $points) {
                $rank++;
            }
        }

        return $rank;
    }

    protected function dateRange(string $filter): array
    {
        $now = now();

        return match ($filter) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->subYears(50), $now->copy()],
        };
    }
}
