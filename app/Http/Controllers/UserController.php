<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;
use App\Models\User;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user(); // Get the currently logged-in user
        $contributions = $user->words()->latest()->paginate(10); // Fetch user's contributions
        $referrals = $user->referrals()->get();

        return view('dashboard', compact('user', 'contributions', 'referrals'));
    }

    public function show(User $user)
    {
        $contributions = $user->words()->latest()->paginate(10); // Fetch user's words
        return view('users.show', compact('user', 'contributions'));
    }
    
}

