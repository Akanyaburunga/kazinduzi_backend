<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProverbRequest;
use App\Http\Requests\Admin\UpdateProverbRequest;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Support\RiddleHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProverbController extends Controller
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

        $proverbs = $query->paginate(request('per_page', 15));

        $proverbs->getCollection()->transform(function ($proverb) {
            $proverb->success_rate = $proverb->attempts_count > 0
                ? round(($proverb->solved_count / $proverb->attempts_count) * 100, 1)
                : 0;

            return $proverb;
        });

        return response()->json([
            'success' => true,
            'data' => $proverbs,
        ]);
    }

    /**
     * Per-proverb analytics for the admin drill-down.
     */
    public function stats(Proverb $proverb)
    {
        $attempts = $proverb->attempts();
        $solved = (clone $attempts)->where('is_correct', true)->count();
        $total = (clone $attempts)->count();

        $days = 14;
        $byDay = ProverbAttempt::query()
            ->selectRaw('date(created_at) as day, count(*) as attempts, sum(case when is_correct then 1 else 0 end) as correct')
            ->where('proverb_id', $proverb->id)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->day => ['attempts' => (int) $row->attempts, 'correct' => (int) $row->correct],
            ]);

        $wrong = ProverbAttempt::query()
            ->selectRaw('submitted_answer as answer, count(*) as total')
            ->where('proverb_id', $proverb->id)
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
                'proverb' => $proverb->load('category:id,name,slug'),
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
     * Export proverbs (respecting current filters) to CSV.
     */
    public function export(): StreamedResponse
    {
        $rows = $this->filteredQuery()
            ->with('category:id,name')
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)])
            ->orderByDesc('id')
            ->get();

        $filename = 'proverbs-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Question', 'Answer', 'Aliases', 'Difficulty', 'Category', 'Source', 'Suspended', 'Attempts', 'Solved', 'Success %', 'Created at']);

            foreach ($rows as $proverb) {
                $rate = $proverb->attempts_count > 0
                    ? round(($proverb->solved_count / $proverb->attempts_count) * 100, 1)
                    : 0;
                fputcsv($handle, [
                    $proverb->id,
                    $proverb->question,
                    $proverb->answer,
                    $proverb->answer_aliases ?? '',
                    $proverb->difficulty,
                    $proverb->category?->name ?? '',
                    $proverb->source ?? '',
                    $proverb->is_suspended ? 'yes' : 'no',
                    $proverb->attempts_count,
                    $proverb->solved_count,
                    $rate,
                    $proverb->created_at?->toDateTimeString(),
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
        $query = Proverb::query();

        if (request('trashed')) {
            $query->onlyTrashed();
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%")
                    ->orWhere('answer_aliases', 'like', "%{$search}%");
            });
        }

        if (($status = request('status')) && in_array($status, ['active', 'suspended'], true)) {
            $query->where('is_suspended', $status === 'suspended');
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if (($difficulty = request('difficulty')) && in_array($difficulty, Proverb::DIFFICULTIES, true)) {
            $query->where('difficulty', $difficulty);
        }

        return $query;
    }

    public function store(StoreProverbRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->answer, $request->category_id)) {
            return $this->duplicateResponse($duplicate);
        }

        $proverb = Proverb::create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'answer_aliases' => $request->answer_aliases,
            'difficulty' => $request->difficulty ?? 'easy',
            'source' => $request->source,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $proverb->load('category:id,name,slug', 'creator:id,name'),
        ], 201);
    }

    public function show(Proverb $proverb)
    {
        $proverb->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $proverb]);
    }

    public function update(UpdateProverbRequest $request, Proverb $proverb)
    {
        $data = $request->only(['category_id', 'question', 'difficulty', 'answer_aliases', 'source']);
        if ($request->filled('answer')) {
            $data['answer'] = RiddleHelper::normalize($request->answer);
        }

        $effectiveAnswer = $data['answer'] ?? $proverb->answer;
        $effectiveCategory = $request->has('category_id') ? $request->category_id : $proverb->category_id;

        if ($this->answersDiffer($effectiveAnswer, $effectiveCategory, $proverb)) {
            if ($duplicate = $this->findDuplicate($effectiveAnswer, $effectiveCategory, $proverb)) {
                return $this->duplicateResponse($duplicate);
            }
        }

        $proverb->update($data);

        return response()->json([
            'success' => true,
            'data' => $proverb->load('category:id,name,slug', 'creator:id,name'),
        ]);
    }

    public function destroy(Proverb $proverb)
    {
        $proverb->delete();

        return response()->json(['success' => true, 'message' => 'Proverb deleted.']);
    }

    public function suspend(Request $request, Proverb $proverb)
    {
        $proverb->update([
            'is_suspended' => true,
            'suspended_reason' => $request->input('reason'),
        ]);

        return response()->json(['success' => true, 'message' => 'Proverb suspended.']);
    }

    public function unsuspend(Proverb $proverb)
    {
        $proverb->update([
            'is_suspended' => false,
            'suspended_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Proverb unsuspended.']);
    }

    /**
     * Restore a soft-deleted proverb.
     */
    public function restore(int $id)
    {
        $proverb = Proverb::onlyTrashed()->findOrFail($id);
        $proverb->restore();

        return response()->json(['success' => true, 'message' => 'Proverb restored.']);
    }

    /**
     * Find an existing proverb with the same normalized answer in the same category.
     */
    private function findDuplicate(string $answer, ?int $categoryId, ?Proverb $ignore = null): ?Proverb
    {
        return Proverb::query()
            ->where('answer', RiddleHelper::normalize($answer))
            ->where('category_id', $categoryId)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();
    }

    private function answersDiffer(string $answer, ?int $categoryId, Proverb $proverb): bool
    {
        return RiddleHelper::normalize($answer) !== $proverb->answer
            || (int) $categoryId !== (int) $proverb->category_id;
    }

    private function duplicateResponse(Proverb $duplicate)
    {
        return response()->json([
            'message' => 'A proverb with this answer already exists in this category.',
            'errors' => [
                'answer' => ["A proverb with this answer already exists in \"{$duplicate->question}\"."],
            ],
            'duplicate' => [
                'id' => $duplicate->id,
                'question' => $duplicate->question,
            ],
        ], 422);
    }
}