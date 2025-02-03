<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaderboardController extends Controller
{
    public function index()
    {
        $topUsers = User::orderByDesc('reputation')->take(10)->get();

        return view('leaderboard.index', compact('topUsers'));
    }
}

