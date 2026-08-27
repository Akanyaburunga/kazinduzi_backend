<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRiddleRequest;
use App\Http\Requests\Admin\UpdateRiddleRequest;
use App\Models\Riddle;
use App\Support\RiddleHelper;

class RiddleController extends Controller
{
    /**
     * Paginated, searchable list including answers (admin only).
     */
    public function index()
    {
        $query = Riddle::query()
            ->with(['category:id,name,slug', 'creator:id,name'])
            ->withCount('attempts');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $sort = request('sort');
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';
        if (in_array($sort, ['id', 'question', 'answer', 'is_suspended', 'created_at'], true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->latest();
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(request('per_page', 15)),
        ]);
    }

    public function store(StoreRiddleRequest $request)
    {
        $riddle = Riddle::create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'hint' => $request->hint,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $riddle->load('category:id,name,slug', 'creator:id,name')], 201);
    }

    public function show(Riddle $riddle)
    {
        $riddle->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $riddle]);
    }

    public function update(UpdateRiddleRequest $request, Riddle $riddle)
    {
        $data = $request->only(['category_id', 'question', 'hint']);
        if ($request->filled('answer')) {
            $data['answer'] = RiddleHelper::normalize($request->answer);
        }

        $riddle->update($data);

        return response()->json(['success' => true, 'data' => $riddle->load('category:id,name,slug', 'creator:id,name')]);
    }

    public function destroy(Riddle $riddle)
    {
        $riddle->delete();

        return response()->json(['success' => true, 'message' => 'Riddle deleted.']);
    }

    public function suspend(Riddle $riddle)
    {
        $riddle->update(['is_suspended' => true]);

        return response()->json(['success' => true, 'message' => 'Riddle suspended.']);
    }

    public function unsuspend(Riddle $riddle)
    {
        $riddle->update(['is_suspended' => false]);

        return response()->json(['success' => true, 'message' => 'Riddle unsuspended.']);
    }
}
