<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proverb;
use App\Models\ProverbSubmission;
use App\Support\RiddleHelper;
use Illuminate\Http\Request;

class ProverbSubmissionController extends Controller
{
    /**
     * Paginated, filterable list of user-generated proverb submissions.
     */
    public function index()
    {
        $query = ProverbSubmission::with(['user:id,name', 'category:id,name,slug'])
            ->withCount(['proverb' => fn ($q) => $q->withTrashed()])
            ->latest();

        if (($status = request('status')) && in_array($status, ProverbSubmission::STATUSES, true)) {
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
     * Approve a submission: publish it as a live Proverb and close the queue item.
     */
    public function approve(ProverbSubmission $submission)
    {
        if ($submission->status !== ProverbSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $proverb = Proverb::query()
            ->where('answer', RiddleHelper::normalize($submission->answer))
            ->when($submission->category_id, fn ($q) => $q->where('category_id', $submission->category_id))
            ->first();

        if (! $proverb) {
            $proverb = Proverb::create([
                'category_id' => $submission->category_id,
                'question' => $submission->question,
                'answer' => $submission->answer,
                'difficulty' => $submission->difficulty,
                'source' => $submission->source,
                'created_by' => $submission->user_id,
            ]);
        }

        $submission->update([
            'proverb_id' => $proverb->id,
            'status' => ProverbSubmission::STATUS_APPROVED,
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission approved and published.',
            'data' => [
                'submission' => $submission->load('category:id,name,slug'),
                'proverb' => $proverb->load('category:id,name,slug'),
            ],
        ]);
    }

    /**
     * Reject a pending submission (optionally with a reason).
     */
    public function reject(Request $request, ProverbSubmission $submission)
    {
        if ($submission->status !== ProverbSubmission::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Submission already reviewed.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $submission->update([
            'status' => ProverbSubmission::STATUS_REJECTED,
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