<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;

class WordController extends Controller
{
    //

    public function index(Request $request)
    {
        $search = $request->search;
        $query = Word::with('user')->latest();

        if ($search) {
            $query->where('word', 'like', '%' . $search . '%')
                ->orWhere('meaning', 'like', '%' . $search . '%');
        }

        $words = $query->paginate(10);

        return view('words.index', compact('words', 'search'));
    }

    public function create()
    {
        return view('words.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'meaning' => 'required|string',
        ]);

        $word = Word::create([
            'word' => $validated['word'],
            'meaning' => $validated['meaning'],
            'user_id' => auth()->id(),
        ]);

        // Clear the top contributors cache
        Cache::forget('top_contributors');

        return redirect()->route('words.index')->with('success', 'Word added successfully!');
    }

    public function show(Word $word)
    {
        return view('words.show', compact('word'));
    }

}
