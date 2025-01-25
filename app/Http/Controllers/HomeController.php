<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Word;

class HomeController extends Controller
{
    public function index()
    {
        // Fetch recent contributions with pagination (10 per page)
        $recentWords = Word::with(['meanings' => function ($query) {
            $query->withCount('votes') // Count votes dynamically
                  ->orderByDesc('votes_count') // Use the calculated column
                  ->orderBy('created_at') // Break ties by creation date
                  ->limit(1);
        }])->paginate(10);

        // Fetch top contributors with caching (cache for 60 minutes)
        $topContributors = cache()->remember('top_contributors', now()->addMinutes(60), function () {
            return User::withCount('words')
                ->orderBy('words_count', 'desc')
                ->take(5)
                ->get();
        });

        return view('home', compact('recentWords', 'topContributors'));
    }
}

