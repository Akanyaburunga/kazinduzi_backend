<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $featuredWords = Word::where('is_suspended', false)->with('meanings')->orderByDesc('created_at')->take(3)->get();
        //$trendingWords = Word::where('is_suspended', false)->orderByDesc('created_at')->take(5)->get();
        $trendingWords = Word::where('is_suspended', false)
            ->whereHas('meanings', function ($query) {
                $query->where('is_suspended', false);
            })
            ->with(['meanings' => function ($query) {
                $query->withCount(['votes as recent_votes_count' => function ($q) {
                    $q->where('created_at', '>=', now()->subDays(7));
                }]);
            }])
            ->get()
            ->sortByDesc(function ($word) {
                // Sum up recent_votes_count for all meanings of a word
                return $word->meanings->sum('recent_votes_count');
            })
            ->take(5)
            ->values(); // Re-index the collection

        // 🔁 Fallback: Use recent words if trending is empty
        if ($trendingWords->isEmpty()) {
            $trendingWords = Word::where('is_suspended', false)
                ->latest()
                ->take(5)
                ->get();
        }
        
        $recentContributions = Word::where('is_suspended', false)->with('meanings', 'user')->latest()->take(5)->get();
        $topContributors = User::where('is_banned', false)->orderByDesc('reputation')->take(5)->get();

        return view('home', compact('featuredWords', 'trendingWords', 'recentContributions', 'topContributors'));
    }

}

