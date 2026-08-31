<?php

namespace App\Http\Controllers\Api\Joke;

use App\Http\Controllers\Controller;
use App\Http\Requests\Joke\StoreJokeSubmissionRequest;
use App\Models\Joke;
use App\Models\JokeSubmission;
use Illuminate\Http\Request;

class JokeSubmissionController extends Controller
{
    /**
     * List the authenticated user's own joke submissions.
     */
    public function index(Request $request)
    {
        $submissions = $request->user()
            ->jokeSubmissions()
            ->with('category:id,name,slug')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions->map(fn (JokeSubmission $submission) => $this->payload($submission)),
        ]);
    }

    /**
     * Submit a user-generated joke to the moderation queue.
     */
    public function store(StoreJokeSubmissionRequest $request)
    {
        if ($duplicate = $this->findDuplicate($request->punchline)) {
            return $this->duplicateResponse($duplicate);
        }

        $submission = $request->user()->jokeSubmissions()->create([
            'category_id' => $request->category_id,
            'setup' => $request->setup,
            'punchline' => $request->punchline,
            'source' => $request->source,
            'status' => JokeSubmission::STATUS_PENDING,
        ]);

        $submission->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $this->payload($submission),
        ], 201);
    }

    /**
     * Find an existing published joke with the same punchline.
     */
    private function findDuplicate(string $punchline): ?Joke
    {
        return Joke::query()
            ->where('punchline', trim($punchline))
            ->first();
    }

    private function duplicateResponse(Joke $duplicate)
    {
        return response()->json([
            'message' => 'A joke with this punchline already exists.',
            'errors' => [
                'punchline' => ["This joke has already been submitted and published as \"{$duplicate->setup}\"."],
            ],
        ], 422);
    }

    private function payload(JokeSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'category_id' => $submission->category_id,
            'category' => $submission->category
                ? ['id' => $submission->category->id, 'name' => $submission->category->name, 'slug' => $submission->category->slug]
                : null,
            'setup' => $submission->setup,
            'source' => $submission->source,
            'status' => $submission->status,
            'rejection_reason' => $submission->rejection_reason,
            'created_at' => $submission->created_at,
        ];
    }
}