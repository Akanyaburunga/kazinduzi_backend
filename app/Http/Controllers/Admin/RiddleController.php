<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRiddleRequest;
use App\Http\Requests\Admin\UpdateRiddleRequest;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Support\RiddleHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiddleController extends Controller
{
    /**
     * Paginated, searchable list including answers (admin only).
     */
    public function index()
    {
        $query = $this->filteredQuery()
            ->with(['category:id,name,slug', 'creator:id,name'])
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)]);

        $sort = request('sort');
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';
        if (in_array($sort, ['id', 'question', 'answer', 'difficulty', 'is_suspended', 'created_at', 'attempts_count', 'solved_count'], true)) {
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

    /**
     * Per-riddle analytics for the admin drill-down.
     */
    public function stats(Riddle $riddle)
    {
        $attempts = $riddle->attempts();
        $solved = (clone $attempts)->where('is_correct', true)->count();
        $total = (clone $attempts)->count();

        $days = 14;
        $byDay = RiddleAttempt::query()
            ->selectRaw('date(created_at) as day, count(*) as attempts, sum(case when is_correct then 1 else 0 end) as correct')
            ->where('riddle_id', $riddle->id)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->day => ['attempts' => (int) $row->attempts, 'correct' => (int) $row->correct],
            ]);

        $wrong = RiddleAttempt::query()
            ->selectRaw('submitted_answer as answer, count(*) as total')
            ->where('riddle_id', $riddle->id)
            ->where('is_correct', false)
            ->whereNotNull('submitted_answer')
            ->where('submitted_answer', '!=', '')
            ->groupBy('submitted_answer')
            ->orderByDesc('total')
            ->limit(10)
            ->get(['answer', 'total']);

        return response()->json([
            'success' => true,
            'data' => [
                'riddle' => $riddle->load('category:id,name,slug'),
                'attempts_total' => $total,
                'solved_count' => $solved,
                'success_rate' => $total > 0 ? round(($solved / $total) * 100, 1) : 0,
                'attempts_by_day' => $byDay,
                'wrong_answers' => $wrong,
                'report_days' => $days,
            ],
        ]);
    }

    /**
     * Export riddles (respecting current filters) to CSV.
     */
    public function export(): StreamedResponse
    {
        $rows = $this->filteredQuery()
            ->with('category:id,name')
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)])
            ->orderByDesc('id')
            ->get();

        $filename = 'riddles-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Question', 'Answer', 'Difficulty', 'Category', 'Source', 'Suspended', 'Attempts', 'Solved', 'Success %', 'Created at']);

            foreach ($rows as $riddle) {
                $rate = $riddle->attempts_count > 0
                    ? round(($riddle->solved_count / $riddle->attempts_count) * 100, 1)
                    : 0;
                fputcsv($handle, [
                    $riddle->id,
                    $riddle->question,
                    $riddle->answer,
                    $riddle->difficulty,
                    $riddle->category?->name ?? '',
                    $riddle->source ?? '',
                    $riddle->is_suspended ? 'yes' : 'no',
                    $riddle->attempts_count,
                    $riddle->solved_count,
                    $rate,
                    $riddle->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Base query with the same filters used by the list view.
     */
    private function filteredQuery()
    {
        $query = Riddle::query();

        if (request('trashed')) {
            $query->onlyTrashed();
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if (($status = request('status')) && in_array($status, ['active', 'suspended'], true)) {
            $query->where('is_suspended', $status === 'suspended');
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if (($difficulty = request('difficulty')) && in_array($difficulty, Riddle::DIFFICULTIES, true)) {
            $query->where('difficulty', $difficulty);
        }

        return $query;
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

    public function suspend(Request $request, Riddle $riddle)
    {
        $riddle->update([
            'is_suspended' => true,
            'suspended_reason' => $request->input('reason'),
        ]);

        return response()->json(['success' => true, 'message' => 'Riddle suspended.']);
    }

    public function unsuspend(Riddle $riddle)
    {
        $riddle->update([
            'is_suspended' => false,
            'suspended_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Riddle unsuspended.']);
    }

    /**
     * Restore a soft-deleted riddle.
     */
    public function restore(int $id)
    {
        $riddle = Riddle::onlyTrashed()->findOrFail($id);
        $riddle->restore();

        return response()->json(['success' => true, 'message' => 'Riddle restored.']);
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
