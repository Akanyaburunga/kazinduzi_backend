<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;
use App\Models\Meaning;
use App\Policies\WordPolicy;
use Cache;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use Throwable;

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
            'type' => 'required|string|max:100',
            'meaning' => 'required|string|max:10000',
        ], [
            'word.unique' => 'This word has already been added. Please consider adding a new meaning instead.',
        ]);

        $existingWord = Word::whereRaw('LOWER(word) = ?', [strtolower($validated['word'])])->first();

        if ($existingWord) {
            Log::info("Duplicate word attempted: {$validated['word']}");
            return redirect()->route('words.show', $existingWord->id)
                ->with('info', 'This word already exists. You can add your meaning here.');
        }

        try {
            $word = Word::create([
                'word' => $validated['word'],
                'type' => $validated['type'],
                'user_id' => auth()->id(),
            ]);

            $converter = new CommonMarkConverter();
            $html = $converter->convertToHtml($validated['meaning']);

            $word->meanings()->create([
                'meaning' => $validated['meaning'],
                'user_id' => auth()->id(),
            ]);

            auth()->user()->updateReputation(10, 'Submitted a new word', $word);

            Cache::forget('top_contributors');

            Log::info("Word created successfully", [
                'word_id' => $word->id,
                'user_id' => auth()->id(),
                'word' => $validated['word']
            ]);

            return redirect()->route('words.index')->with('success', 'Word and meaning added successfully!');
        } catch (Throwable $e) {
            Log::error("Failed to save word", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'input' => $validated,
            ]);

            return back()->withErrors(['error' => 'An error occurred while saving the word. Please try again.']);
        }
    }

    public function show(Word $word)
    {
        $word->load('meanings');

        $environment = new Environment([
            'commonmark' => [
                'enable_em' => true,
                'enable_strong' => true,
                'use_asterisk' => true,
                'use_underscore' => true,
                'unordered_list_markers' => ['-', '*', '+'],
                'enable_html_input' => true,
                'allow_unsafe_links' => false,
                'renderer' => [
                    'soft_break' => "<br>\n",  // ✅ Render single newlines as <br>
                ],
            ],
        ]);
        
        $environment->addExtension(new CommonMarkCoreExtension());
        
        $converter = new CommonMarkConverter([], $environment);

        foreach ($word->meanings as $meaning) {
            $meaning->html = $converter->convertToHtml($meaning->meaning);
        }

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
        ]);

        // Update the meaning for the authenticated user
        $userMeaning = Meaning::where('word_id', $word->id)
            ->where('user_id', auth()->id())
            ->first();
        if ($userMeaning) {
            $userMeaning->update([
                'meaning' => $request->meaning,
            ]);
        } else {
            // If the user doesn't have a meaning, create a new one
            $word->meanings()->create([
                'meaning' => $request->meaning,
                'user_id' => auth()->id(),
            ]);
        }

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
