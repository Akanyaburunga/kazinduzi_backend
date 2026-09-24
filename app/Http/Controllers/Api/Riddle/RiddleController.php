<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\StoreRiddleRequest;
use App\Http\Requests\Riddle\UpdateRiddleRequest;
use App\Models\Riddle;
use App\Support\RiddleHelper;

class RiddleController extends Controller
{
    use IsCurator;

    /**
     * List all riddles (curator view, includes answers).
     */
    public function index()
    {
        $this->authorizeCurator();

        $riddles = Riddle::with(['category:id,name,slug', 'creator:id,name'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $riddles]);
    }

    /**
     * Show a single riddle with its answer (curator only).
     */
    public function show(Riddle $riddle)
    {
        $this->authorizeCurator();

        $riddle->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $riddle]);
    }

    /**
     * Create a riddle. The answer is normalized on save.
     */
    public function store(StoreRiddleRequest $request)
    {
        $this->authorizeCurator();

        $riddle = Riddle::create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'hint' => $request->hint,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $riddle], 201);
    }

    /**
     * Update a riddle. Re-normalize the answer if it was provided.
     */
    public function update(UpdateRiddleRequest $request, Riddle $riddle)
    {
        $this->authorizeCurator();

        $data = $request->only(['category_id', 'question', 'hint']);
        if ($request->filled('answer')) {
            $data['answer'] = RiddleHelper::normalize($request->answer);
        }

        $riddle->update($data);

        return response()->json(['success' => true, 'data' => $riddle]);
    }

    /**
     * Delete a riddle.
     */
    public function destroy(Riddle $riddle)
    {
        $this->authorizeCurator();

        $riddle->delete();

        return response()->json(['success' => true, 'message' => 'Riddle deleted.']);
    }

    /**
     * Suspend a riddle (hide from game clients).
     */
    public function suspend(Riddle $riddle)
    {
        $this->authorizeCurator();

        $riddle->update(['is_suspended' => true]);

        return response()->json(['success' => true, 'message' => 'Riddle suspended.']);
    }

    /**
     * Un-suspend a riddle.
     */
    public function unsuspend(Riddle $riddle)
    {
        $this->authorizeCurator();

        $riddle->update(['is_suspended' => false]);

        return response()->json(['success' => true, 'message' => 'Riddle unsuspended.']);
    }
}
