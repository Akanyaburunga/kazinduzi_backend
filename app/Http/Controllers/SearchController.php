<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Word;

class SearchController extends Controller
{
    public function autocomplete(Request $request)
    {
        $search = $request->get('query', '');

        // Fetch matching words with a limit for performance
        $results = Word::where('word', 'LIKE', "%{$search}%")
                        ->orderBy('word')
                        ->limit(10)
                        ->pluck('word');

        return response()->json($results);
    }
}

