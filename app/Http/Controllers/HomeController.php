<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Word;

class HomeController extends Controller
{
    public function index()
    {
        $featuredWords = Word::where('is_suspended', false)->with('meanings')->orderByDesc('created_at')->take(3)->get();
        $trendingWords = Word::where('is_suspended', false)->orderByDesc('created_at')->take(5)->get();
        $recentContributions = Word::where('is_suspended', false)->with('meanings', 'user')->latest()->take(5)->get();
        $topContributors = User::where('is_banned', false)->orderByDesc('reputation')->take(5)->get();

        return view('home', compact('featuredWords', 'trendingWords', 'recentContributions', 'topContributors'));
    }

}

