<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Word;
use App\Policies\WordPolicy;
use Cache;
use App\Http\Controllers\Controller;

class WordController extends Controller
{
    /**
     * Display a listing of words with search functionality.
     */
    public function index(Request $request)
    {
        $query = Word::with(['meanings.user'])->orderBy('word', 'asc'); // ✅ Load meanings and contributor's user data

        // 🔍 Apply search filter if provided
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('word', 'LIKE', "%{$searchTerm}%");
        }

        $words = $query->get(); // Retrieve words with meanings

        // Format the response to include contributor's name for each meaning
        $words->each(function ($word) {
            $word->meanings->each(function ($meaning) {
                $meaning->contributor_name = $meaning->user->name; // Add contributor name
            });
        });

        return response()->json([
            'success' => true,
            'data' => $words,
        ]);
    }

}
