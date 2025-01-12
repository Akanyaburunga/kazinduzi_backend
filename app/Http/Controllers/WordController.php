<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;

class WordController extends Controller
{
    //

    public function index()
    {
        $words = Word::with('user')->latest()->get();
        return view('words.index', compact('words'));
    }

    public function create()
    {
        return view('words.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'word' => 'required|string|unique:words|max:255',
            'meaning' => 'required|string',
        ]);

        Word::create([
            'word' => $request->word,
            'meaning' => $request->meaning,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('words.index')->with('success', 'Word submitted successfully!');
    }

}
