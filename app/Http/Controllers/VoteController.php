<?php

namespace App\Http\Controllers;

use App\Models\Meaning;
use App\Models\Vote;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function store(Request $request, Meaning $meaning)
    {
        $request->validate([
            'vote' => 'required|in:up,down',
        ]);

        $vote = Vote::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'meaning_id' => $meaning->id,
            ],
            [
                'vote' => $request->vote,
            ]
        );

        return redirect()->back()->with('success', 'Your vote has been recorded.');
    }
}
