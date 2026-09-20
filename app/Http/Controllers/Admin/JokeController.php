<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJokeRequest;
use App\Http\Requests\Admin\UpdateJokeRequest;
use App\Models\Joke;
use App\Models\JokeAttempt;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JokeController extends Controller
{
    /**
     * Paginated, searchable list including punchlines (admin only).
     */
    public function index()
    {
        $query = $this->filteredQuery()
            ->with(['category:id,name,slug', 'creator:id,name'])
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)]);

        $sort = request('sort');
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';
        if (in_array($sort, ['id', 'setup', 'punchline', 'is_suspended', 'created_at', 'attempts_count', 'solved_count'], true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->latest();
        }

        $jokes = $query->paginate(request('per_page', 15));

        $jokes->getCollection()->transform(function ($joke) {
            $joke->success_rate = $joke->attempts_count > 0
                ? round(($joke->solved_count / $joke->attempts_count) * 100, 1)
                : 0;

            return $joke;
        });

        return response()->json([
            'success' => true,
            'data' => $jokes,
        ]);
    }

    /**
     * Per-joke analytics for the admin drill-down.
     */
    public function stats(Joke $joke)
    {
        $attempts = $joke->attempts();
        $solved = (clone $attempts)->where('is_correct', true)->count();
        $total = (clone $attempts)->count();

        $days = 14;
        $byDay = JokeAttempt::query()
            ->selectRaw('date(created_at) as day, count(*) as attempts, sum(case when is_correct then 1 else 0 end) as correct')
            ->where('joke_id', $joke->id)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->day => ['attempts' => (int) $row->attempts, 'correct' => (int) $row->correct],
            ]);

        $wrong = JokeAttempt::query()
            ->selectRaw('submitted_answer as answer, count(*) as total')
            ->where('joke_id', $joke->id)
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
                'joke' => $joke->load('category:id,name,slug'),
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
     * Export jokes (respecting current filters) to CSV.
     */
    public function export(): StreamedResponse
    {
        $rows = $this->filteredQuery()
            ->with('category:id,name')
            ->withCount('attempts')
            ->withCount(['attempts as solved_count' => fn ($q) => $q->where('is_correct', true)])
            ->orderByDesc('id')
            ->get();

        $filename = 'jokes-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Setup', 'Punchline', 'Distractors', 'Category', 'Source', 'Suspended', 'Attempts', 'Solved', 'Success %', 'Created at']);

            foreach ($rows as $joke) {
                $rate = $joke->attempts_count > 0
                    ? round(($joke->solved_count / $joke->attempts_count) * 100, 1)
                    : 0;
                fputcsv($handle, [
                    $joke->id,
                    $joke->setup,
                    $joke->punchline,
                    implode(' | ', $joke->distractors ?? []),
                    $joke->category?->name ?? '',
                    $joke->source ?? '',
                    $joke->is_suspended ? 'yes' : 'no',
                    $joke->attempts_count,
                    $joke->solved_count,
                    $rate,
                    $joke->created_at?->toDateTimeString(),
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
        $query = Joke::query();

        if (request('trashed')) {
            $query->onlyTrashed();
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('setup', 'like', "%{$search}%")
                    ->orWhere('punchline', 'like', "%{$search}%");
            });
        }

        if (($status = request('status')) && in_array($status, ['active', 'suspended'], true)) {
            $query->where('is_suspended', $status === 'suspended');
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        return $query;
    }

    public function store(StoreJokeRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->punchline)) {
            return $this->duplicateResponse($duplicate);
        }

        $joke = Joke::create([
            'category_id' => $request->category_id,
            'setup' => $request->setup,
            'punchline' => $request->punchline,
            'distractors' => $request->distractors,
            'source' => $request->source,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $joke->load('category:id,name,slug', 'creator:id,name'),
        ], 201);
    }

    public function show(Joke $joke)
    {
        $joke->load(['category:id,name,slug', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $joke]);
    }

    public function update(UpdateJokeRequest $request, Joke $joke)
    {
        $data = $request->only(['category_id', 'setup', 'punchline', 'distractors', 'source']);
        $data['distractors'] = $request->filled('distractors') && $request->distractors !== null
            ? array_values(array_unique(array_map('trim', (array) $request->distractors)))
            : null;

        if ($request->has('punchline') && $request->filled('punchline')) {
            if ($duplicate = $this->findDuplicate($request->punchline, $joke)) {
                return $this->duplicateResponse($duplicate);
            }
        }

        $joke->update($data);

        return response()->json([
            'success' => true,
            'data' => $joke->load('category:id,name,slug', 'creator:id,name'),
        ]);
    }

    public function destroy(Joke $joke)
    {
        $joke->delete();

        return response()->json(['success' => true, 'message' => 'Joke deleted.']);
    }

    public function suspend(Request $request, Joke $joke)
    {
        $joke->update([
            'is_suspended' => true,
            'suspended_reason' => $request->input('reason'),
        ]);

        return response()->json(['success' => true, 'message' => 'Joke suspended.']);
    }

    public function unsuspend(Joke $joke)
    {
        $joke->update([
            'is_suspended' => false,
            'suspended_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Joke unsuspended.']);
    }

    /**
     * Restore a soft-deleted joke.
     */
    public function restore(int $id)
    {
        $joke = Joke::onlyTrashed()->findOrFail($id);
        $joke->restore();

        return response()->json(['success' => true, 'message' => 'Joke restored.']);
    }

    /**
     * Find an existing joke with the same punchline.
     */
    private function findDuplicate(string $punchline, ?Joke $ignore = null): ?Joke
    {
        return Joke::query()
            ->where('punchline', trim($punchline))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();
    }

    private function duplicateResponse(Joke $duplicate)
    {
        return response()->json([
            'message' => 'A joke with this punchline already exists.',
            'errors' => [
                'punchline' => ["A joke with this punchline already exists with the setup \"{$duplicate->setup}\"."],
            ],
            'duplicate' => [
                'id' => $duplicate->id,
                'setup' => $duplicate->setup,
            ],
        ], 422);
    }
}