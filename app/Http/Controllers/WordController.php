<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;
use App\Policies\WordPolicy;
use Cache;

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

    public function edit(Word $word)
    {
        $this->authorize('update', $word); // Ensure the user owns the word
        return view('words.edit', compact('word'));
    }

    public function update(Request $request, Word $word)
    {
        $this->authorize('update', $word);

        $request->validate([
            'word' => 'required|string|max:255',
            'meaning' => 'required|string',
        ]);

        $word->update([
            'word' => $request->word,
            'meaning' => $request->meaning,
        ]);

        return redirect()->route('dashboard')->with('success', 'Word updated successfully!');
    }

    public function destroy(Word $word)
    {
        $this->authorize('delete', $word);
        $word->delete();

        return redirect()->route('dashboard')->with('success', 'Word deleted successfully!');
    }


}
