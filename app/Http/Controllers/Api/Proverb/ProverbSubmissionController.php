<?php

namespace App\Http\Controllers\Api\Proverb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proverb\StoreProverbSubmissionRequest;
use App\Models\Proverb;
use App\Models\ProverbSubmission;
use App\Support\RiddleHelper as SupportRiddleHelper;
use Illuminate\Http\Request;

class ProverbSubmissionController extends Controller
{
    /**
     * List the authenticated user's own proverb submissions.
     */
    public function index(Request $request)
    {
        $submissions = $request->user()
            ->proverbSubmissions()
            ->with('category:id,name,slug')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions->map(fn (ProverbSubmission $submission) => $this->payload($submission)),
        ]);
    }

    /**
     * Submit a user-generated proverb to the moderation queue.
     */
    public function store(StoreProverbSubmissionRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->answer, $request->category_id)) {
            return $this->duplicateResponse($duplicate);
        }

        $submission = $request->user()->proverbSubmissions()->create([
            'category_id' => $request->category_id,
            'question' => $request->question,
            'answer' => $request->answer,
            'difficulty' => $request->difficulty ?? 'medium',
            'source' => $request->source,
            'status' => ProverbSubmission::STATUS_PENDING,
        ]);

        $submission->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->payload($submission),
        ], 201);
    }

    /**
     * Find an existing published proverb with the same normalized answer.
     */
    private function findDuplicate(string $answer, ?int $categoryId): ?Proverb
    {
        return Proverb::query()
            ->where('answer', SupportRiddleHelper::normalize($answer))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->first();
    }

    private function duplicateResponse(Proverb $duplicate)
    {
        return response()->json([
            'message' => 'A proverb with this answer already exists.',
            'errors' => [
                'answer' => ["This proverb has already been submitted and published as \"{$duplicate->question}\"."],
            ],
        ], 422);
    }

    private function payload(ProverbSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'category_id' => $submission->category_id,
            'category' => $submission->category
                ? ['id' => $submission->category->id, 'name' => $submission->category->name, 'slug' => $submission->category->slug]
                : null,
            'question' => $submission->question,
            'difficulty' => $submission->difficulty,
            'source' => $submission->source,
            'status' => $submission->status,
            'rejection_reason' => $submission->rejection_reason,
            'created_at' => $submission->created_at,
        ];
    }
}