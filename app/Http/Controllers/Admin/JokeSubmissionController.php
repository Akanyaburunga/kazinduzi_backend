<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use App\Models\JokeSubmission;
use Illuminate\Http\Request;

class JokeSubmissionController extends Controller
{
    /**
     * Paginated, filterable list of user-generated joke submissions.
     */
    public function index()
    {
        $query = JokeSubmission::with(['user:id,name', 'category:id,name,slug'])
            ->withCount(['joke' => fn ($q) => $q->withTrashed()])
            ->latest();

        if (($status = request('status')) && in_array($status, JokeSubmission::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search = request('search')) {
            $query->where('setup', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(request('per_page', 15)),
        ]);
    }

    /**
     * Approve a submission: publish it as a live Joke and close the queue item.
     */
    public function approve(JokeSubmission $submission)
    {
        if ($submission->status !== JokeSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $joke = Joke::query()
            ->where('punchline', trim($submission->punchline))
            ->first();

        if (! $joke) {
            $joke = Joke::create([
                'category_id' => $submission->category_id,
                'setup' => $submission->setup,
                'punchline' => $submission->punchline,
                'distractors' => null,
                'source' => $submission->source,
                'created_by' => $submission->user_id,
            ]);
        }

        $submission->update([
            'joke_id' => $joke->id,
            'status' => JokeSubmission::STATUS_APPROVED,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission approved and published.',
            'data' => [
                'submission' => $submission->load('category:id,name,slug'),
                'joke' => $joke->load('category:id,name,slug'),
            ],
        ]);
    }

    /**
     * Reject a pending submission (optionally with a reason).
     */
    public function reject(Request $request, JokeSubmission $submission)
    {
        if ($submission->status !== JokeSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $submission->update([
            'status' => JokeSubmission::STATUS_REJECTED,
            'rejection_reason' => $data['reason'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission rejected.',
            'data' => $submission->load('category:id,name,slug'),
        ]);
    }
}