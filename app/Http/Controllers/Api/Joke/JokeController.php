<?php

namespace App\Http\Controllers\Api\Joke;

use App\Http\Controllers\Controller;
use App\Http\Requests\Joke\StoreJokeRequest;
use App\Http\Requests\Joke\UpdateJokeRequest;
use App\Models\Joke;

class JokeController extends Controller
{
    use \App\Http\Controllers\Api\Riddle\IsCurator;

    /**
     * List all jokes (curator view, includes punchlines).
     */
    public function index()
    {
        $this->authorizeCurator();

        $jokes = Joke::with(['category:id,name,slug', 'creator:id,name'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $jokes]);
    }

    /**
     * Show a single joke (curator only).
     */
    public function show(Joke $joke)
    {
        $this->authorizeCurator();

        $joke->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $joke]);
    }

    /**
     * Create a joke.
     */
    public function store(StoreJokeRequest $request)
    {
        $this->authorizeCurator();

        $joke = Joke::create([
            'category_id' => $request->category_id,
            'setup' => $request->setup,
            'punchline' => $request->punchline,
            'distractors' => $request->distractors,
            'source' => $request->source,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $joke], 201);
    }

    /**
     * Update a joke.
     */
    public function update(UpdateJokeRequest $request, Joke $joke)
    {
        $this->authorizeCurator();

        $joke->update($request->only(['category_id', 'setup', 'punchline', 'distractors', 'source']));

        return response()->json(['success' => true, 'data' => $joke]);
    }

    /**
     * Delete a joke.
     */
    public function destroy(Joke $joke)
    {
        $this->authorizeCurator();

        $joke->delete();

        return response()->json(['success' => true, 'message' => 'Joke deleted.']);
    }

    /**
     * Suspend a joke (hide from game clients).
     */
    public function suspend(Joke $joke)
    {
        $this->authorizeCurator();

        $joke->update(['is_suspended' => true]);

        return response()->json(['success' => true, 'message' => 'Joke suspended.']);
    }

    /**
     * Un-suspend a joke.
     */
    public function unsuspend(Joke $joke)
    {
        $this->authorizeCurator();

        $joke->update(['is_suspended' => false]);

        return response()->json(['success' => true, 'message' => 'Joke unsuspended.']);
    }
}