<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;
use App\Models\Meaning;
use App\Policies\WordPolicy;
use Cache;

class WordController extends Controller
{
    //

    public function index(Request $request)
    {
        $search = $request->search;

        $words = Word::with(['user', 'meanings'])
        ->where(function ($query) use ($search) {
            if ($search) {
                $query->where('word', 'like', "%{$search}%")
                    ->orWhereHas('meanings', function ($meaningQuery) use ($search) {
                        $meaningQuery->where('meaning', 'like', "%{$search}%");
                    });
            }
        })
        ->latest()
        ->paginate(10);

        return view('words.index', compact('words', 'search'));
    }

    public function create()
    {
        return view('words.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255|unique:words,word',
            'type' => 'required|string|max:50',
            'meaning' => 'required|string|max:1000',
        ], [
            'word.unique' => 'This word has already been added. Please consider adding a new meaning instead.',
        ]);

        $existingWord = Word::whereRaw('LOWER(word) = ?', [strtolower($validated['word'])])->first();

        if ($existingWord) {
            // Redirect to the page to add a meaning for this word
            return redirect()->route('words.show', $existingWord->id)
                ->with('info', 'This word already exists. You can add your meaning here.');
        }

        $word = Word::create([
            'word' => $validated['word'],
            'type' => $validated['type'],
            'user_id' => auth()->id(),
        ]);

        $word->meanings()->create([
            'meaning' => $validated['meaning'],
            'user_id' => auth()->id(),
        ]);

        auth()->user()->updateReputation(10, 'Submitted a new word', $word); // Add 10 points for submitting a word

        // Clear the top contributors cache
        Cache::forget('top_contributors');

        return redirect()->route('words.index')->with('success', 'Word and meaning added successfully!');
    }

    public function show(Word $word)
    {
        // Remove meanings that are suspended
        $word->meanings = $word->meanings()
            ->where('is_suspended', false)
            ->with('user')
            ->latest()
            ->get();

        return view('words.show', compact('word'));
    }

    public function edit(Word $word)
    {
        $this->authorize('update', $word); // Ensure the user owns the word
    
        // Fetch the meaning that belongs to the authenticated user for this word
        $userMeaning = Meaning::where('word_id', $word->id)
        ->where('user_id', auth()->id())
        ->first(); // Get only one record

        return view('words.edit', compact('word', 'userMeaning'));
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

    public function search(Request $request)
    {
        $query = $request->input('query');
        
        // Perform the search
        $results = Word::with('meanings')
                    ->where('word', 'like', '%' . $query . '%')
                    ->get();

        return view('words.search-results', compact('results', 'query'));
    }

    public function autocomplete(Request $request)
    {
        $query = $request->get('query');

        // Fetch words that match the input query
        $words = Word::where('word', 'like', "%$query%")
                     ->limit(10)
                     ->get(['word', 'slug']);  // You can add more fields if necessary

        return response()->json(['results' => $words]);
    }

}
