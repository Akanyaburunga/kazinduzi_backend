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
     * Display a listing of all words.
     */
    public function index()
    {
        $words = Word::all(); // Retrieve all words from the database

        return response()->json([
            'success' => true,
            'data' => $words,
        ]);
    }

}
