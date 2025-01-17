<?php

namespace App\Http\Controllers;

use App\Models\Word;
use App\Models\Meaning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeaningController extends Controller
{
    public function store(Request $request, Word $word)
    {
        $request->validate([
            'meaning' => 'required|string|max:1000',
        ]);

        // Ensure the user hasn't already added a meaning for this word
        if ($word->meanings()->where('user_id', Auth::id())->exists()) {
            return redirect()->back()->with('error', 'You have already added a meaning for this word.');
        }

        // Add the meaning
        Meaning::create([
            'meaning' => $request->input('meaning'),
            'word_id' => $word->id,
            'user_id' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Your meaning has been added.');
    }
}

