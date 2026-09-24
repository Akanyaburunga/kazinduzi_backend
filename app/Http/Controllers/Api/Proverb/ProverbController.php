<?php

namespace App\Http\Controllers\Api\Proverb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proverb\StoreProverbRequest;
use App\Http\Requests\Proverb\UpdateProverbRequest;
use App\Models\Proverb;
use App\Support\RiddleHelper;

class ProverbController extends Controller
{
    use \App\Http\Controllers\Api\Riddle\IsCurator;

    /**
     * List all proverbs (curator view, includes answers).
     */
    public function index()
    {
        $this->authorizeCurator();

        $proverbs = Proverb::with(['category:id,name,slug', 'creator:id,name'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $proverbs]);
    }

    /**
     * Show a single proverb with its answer (curator only).
     */
    public function show(Proverb $proverb)
    {
        $this->authorizeCurator();

        $proverb->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $proverb]);
    }

    /**
     * Create a proverb. The answer (and aliases) are normalized on save.
     */
    public function store(StoreProverbRequest $request)
    {
        $this->authorizeCurator();

        $proverb = Proverb::create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'answer_aliases' => $request->answer_aliases,
            'difficulty' => $request->difficulty ?? 'medium',
            'source' => $request->source,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $proverb], 201);
    }

    /**
     * Update a proverb (normalize answer if provided).
     */
    public function update(UpdateProverbRequest $request, Proverb $proverb)
    {
        $this->authorizeCurator();

        $data = $request->only(['category_id', 'question', 'source']);
        if ($request->filled('answer')) {
            $data['answer'] = RiddleHelper::normalize($request->answer);
        }
        if ($request->filled('answer_aliases')) {
            $data['answer_aliases'] = RiddleHelper::normalize($request->answer_aliases);
        }
        if ($request->has('difficulty')) {
            $data['difficulty'] = $request->difficulty;
        }

        $proverb->update($data);

        return response()->json(['success' => true, 'data' => $proverb]);
    }

    /**
     * Delete a proverb.
     */
    public function destroy(Proverb $proverb)
    {
        $this->authorizeCurator();

        $proverb->delete();

        return response()->json(['success' => true, 'message' => 'Proverb deleted.']);
    }

    /**
     * Suspend a proverb (hide from game clients).
     */
    public function suspend(Proverb $proverb)
    {
        $this->authorizeCurator();

        $proverb->update(['is_suspended' => true]);

        return response()->json(['success' => true, 'message' => 'Proverb suspended.']);
    }

    /**
     * Un-suspend a proverb.
     */
    public function unsuspend(Proverb $proverb)
    {
        $this->authorizeCurator();

        $proverb->update(['is_suspended' => false]);

        return response()->json(['success' => true, 'message' => 'Proverb unsuspended.']);
    }
}
