<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\StoreSubmissionRequest;
use App\Models\Riddle;
use App\Models\RiddleSubmission;
use App\Support\RiddleHelper as SupportRiddleHelper;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    /**
     * List the authenticated user's own submissions.
     */
    public function index(Request $request)
    {
        $submissions = $request->user()
            ->riddleSubmissions()
            ->with('category:id,name,slug')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions->map(fn (RiddleSubmission $submission) => $this->payload($submission)),
        ]);
    }

    /**
     * Submit a user-generated riddle to the moderation queue.
     */
    public function store(StoreSubmissionRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->answer, $request->category_id)) {
            return $this->duplicateResponse($duplicate);
        }

        $submission = $request->user()->riddleSubmissions()->create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'difficulty' => $request->difficulty ?? 'easy',
            'riddle_type' => $request->riddle_type ?? 'riddle',
            'hint' => $request->hint,
            'hint2' => $request->hint2,
            'source' => $request->source,
            'status' => RiddleSubmission::STATUS_PENDING,
        ]);

        $submission->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->payload($submission),
        ], 201);
    }

    /**
     * Find an existing published riddle with the same normalized answer in
     * the same category (A1 duplicate check blocks obviously duplicated UGC).
     */
    private function findDuplicate(string $answer, ?int $categoryId): ?Riddle
    {
        return Riddle::query()
            ->where('answer', SupportRiddleHelper::normalize($answer))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->first();
    }

    private function duplicateResponse(Riddle $duplicate)
    {
        return response()->json([
            'message' => 'A riddle with this answer already exists.',
            'errors' => [
                'answer' => ["This riddle has already been submitted and published as \"{$duplicate->question}\"."],
            ],
        ], 422);
    }

    private function payload(RiddleSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'category_id' => $submission->category_id,
            'category' => $submission->category
                ? ['id' => $submission->category->id, 'name' => $submission->category->name, 'slug' => $submission->category->slug]
                : null,
            'question' => $submission->question,
            'difficulty' => $submission->difficulty,
            'riddle_type' => $submission->riddle_type,
            'source' => $submission->source,
            'status' => $submission->status,
            'rejection_reason' => $submission->rejection_reason,
            'created_at' => $submission->created_at,
        ];
    }
}
