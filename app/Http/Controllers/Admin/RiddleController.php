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
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)]);

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $sort = request('sort');
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';
        if (in_array($sort, ['id', 'question', 'answer', 'is_suspended', 'created_at', 'attempts_count', 'solved_count'], true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->latest();
        }

        $riddles = $query->paginate(request('per_page', 15));

        $riddles->getCollection()->transform(function ($riddle) {
            $riddle->success_rate = $riddle->attempts_count > 0
                ? round(($riddle->solved_count / $riddle->attempts_count) * 100, 1)
                : 0;

            return $riddle;
        });

        return response()->json([
            'success' => true,
            'data' => $riddles,
        ]);
    }

    public function store(StoreRiddleRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->answer, $request->category_id)) {
            return $this->duplicateResponse($duplicate);
        }

        $riddle = Riddle::create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'difficulty' => $request->difficulty ?? 'easy',
            'hint' => $request->hint,
            'hint2' => $request->hint2,
            'source' => $request->source,
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
        $data = $request->only(['category_id', 'question', 'difficulty', 'hint', 'hint2', 'source']);
        if ($request->filled('answer')) {
            $data['answer'] = RiddleHelper::normalize($request->answer);
        }

        $effectiveAnswer = $data['answer'] ?? $riddle->answer;
        $effectiveCategory = $request->has('category_id') ? $request->category_id : $riddle->category_id;

        if ($this->answersDiffer($effectiveAnswer, $effectiveCategory, $riddle)) {
            if ($duplicate = $this->findDuplicate($effectiveAnswer, $effectiveCategory, $riddle)) {
                return $this->duplicateResponse($duplicate);
            }
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

    /**
     * Find an existing riddle with the same normalized answer in the same category.
     */
    private function findDuplicate(string $answer, ?int $categoryId, ?Riddle $ignore = null): ?Riddle
    {
        return Riddle::query()
            ->where('answer', RiddleHelper::normalize($answer))
            ->where('category_id', $categoryId)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();
    }

    private function answersDiffer(string $answer, ?int $categoryId, Riddle $riddle): bool
    {
        return RiddleHelper::normalize($answer) !== $riddle->answer
            || (int) $categoryId !== (int) $riddle->category_id;
    }

    private function duplicateResponse(Riddle $duplicate)
    {
        return response()->json([
            'message' => 'A riddle with this answer already exists in this category.',
            'errors' => [
                'answer' => ["A riddle with this answer already exists in \"{$duplicate->question}\"."],
            ],
            'duplicate' => [
                'id' => $duplicate->id,
                'question' => $duplicate->question,
            ],
        ], 422);
    }
}
