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
        $query = Word::query();

        // 🔍 Check if there's a search query
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('word', 'LIKE', "%{$searchTerm}%");
        }

        $words = $query->get(); // Retrieve matching words

        return response()->json([
            'success' => true,
            'data' => $words,
        ]);
    }

}
