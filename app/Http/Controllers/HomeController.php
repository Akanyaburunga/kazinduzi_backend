<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Word;

class HomeController extends Controller
{
    public function index()
    {
        $featuredWords = Word::with('meanings')->orderByDesc('created_at')->take(3)->get();
        $trendingWords = Word::orderByDesc('created_at')->take(5)->get();
        $recentContributions = Word::with('meanings', 'user')->latest()->take(5)->get();
        $topContributors = User::orderByDesc('reputation')->take(5)->get();

        return view('home', compact('featuredWords', 'trendingWords', 'recentContributions', 'topContributors'));
    }

}

