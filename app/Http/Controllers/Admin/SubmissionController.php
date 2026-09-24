<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleSubmission;
use App\Support\RiddleHelper;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    /**
     * Paginated, filterable list of user-generated submissions.
     */
    public function index()
    {
        $query = RiddleSubmission::with(['user:id,name', 'category:id,name,slug'])
            ->withCount(['riddle' => fn ($q) => $q->withTrashed()])
            ->latest();

        if (($status = request('status')) && in_array($status, RiddleSubmission::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search = request('search')) {
            $query->where('question', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(request('per_page', 15)),
        ]);
    }

    /**
     * Approve a submission: publish it as a live Riddle and close the queue item.
     */
    public function approve(RiddleSubmission $submission)
    {
        if ($submission->status !== RiddleSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $riddle = Riddle::query()
            ->where('answer', RiddleHelper::normalize($submission->answer))
            ->when($submission->category_id, fn ($q) => $q->where('category_id', $submission->category_id))
            ->first();

        if (! $riddle) {
            $riddle = Riddle::create([
                'category_id' => $submission->category_id,
                'question' => $submission->question,
                'answer' => $submission->answer,
                'difficulty' => $submission->difficulty,
                'riddle_type' => $submission->riddle_type,
                'hint' => $submission->hint,
                'hint2' => $submission->hint2,
                'source' => $submission->source,
                'created_by' => $submission->user_id,
            ]);
        }

        $submission->update([
            'riddle_id' => $riddle->id,
            'status' => RiddleSubmission::STATUS_APPROVED,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission approved and published.',
            'data' => [
                'submission' => $submission->load('category:id,name,slug'),
                'riddle' => $riddle->load('category:id,name,slug'),
            ],
        ]);
    }

    /**
     * Reject a pending submission (optionally with a reason).
     */
    public function reject(Request $request, RiddleSubmission $submission)
    {
        if ($submission->status !== RiddleSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $submission->update([
            'status' => RiddleSubmission::STATUS_REJECTED,
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
