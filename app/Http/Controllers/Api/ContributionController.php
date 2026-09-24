<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContributionStoreRequest;
use App\Models\ModerationLog;
use App\Models\RiddleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Convenience contribution endpoint mirroring the prototype's single-screen
 * "Intererano" form. Routes each contribution to the matching submission
 * store (moderation queue) so the existing admin approval flow is reused.
 */
class ContributionController extends Controller
{
    /**
     * Game mode -> destined submission category name.
     *
     * @var array<string, array{category: string, key: string}>
     */
    protected const TYPE_MAP = [
        'sokwe' => ['category' => 'Ibisokozo', 'key' => 'riddleSubmissions'],
        'hera' => ['category' => 'Imigani', 'key' => 'proverbSubmissions'],
        'tuja' => ['category' => 'Utujajuro', 'key' => 'jokeSubmissions'],
    ];

    public function store(ContributionStoreRequest $request): JsonResponse
    {
        $type = $request->type;
        $user = $request->user();

        if ($type === 'other') {
            return $this->storeGeneric($request, $user);
        }

        $map = static::TYPE_MAP[$type];
        $category = $this->resolveCategory($map['category']);

        if ($type === 'tuja') {
            $submission = $user->jokeSubmissions()->create([
                'category_id' => $category->id,
                'setup' => $request->body,
                'punchline' => $request->answer ?? '',
                'source' => $request->who ?? 'Umuturanyi',
                'status' => 'pending',
            ]);
        } else {
            $submission = $user->{$map['key']}()->create([
                'category_id' => $category->id,
                'question' => $request->body,
                'answer' => $request->answer ?? '',
                'difficulty' => 'medium',
                'source' => $request->who ?? 'Umuturanyi',
                'status' => 'pending',
            ]);
        }

        $submission->load('category:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'id' => $submission->id,
                'status' => $submission->status,
            ],
        ], 201);
    }

    /**
     * 'other' contributions are logged as a generic moderation note.
     */
    protected function storeGeneric(ContributionStoreRequest $request, $user): JsonResponse
    {
        $note = $request->body;
        if ($request->who) {
            $note = "({$request->who}) {$note}";
        }

        ModerationLog::create([
            'action_by' => $user->id,
            'action' => 'contribution_other',
            'reason' => $note,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'other',
                'id' => null,
                'status' => 'pending',
            ],
        ], 201);
    }

    /**
     * Find the category by name/slug, creating it if missing.
     */
    protected function resolveCategory(string $name): RiddleCategory
    {
        $category = RiddleCategory::query()
            ->where('name', $name)
            ->orWhere('slug', Str::slug($name))
            ->first();

        if (! $category) {
            $category = RiddleCategory::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Ibitangwa n'abatanga (Intererano) vy'ivy'{$name}.",
            ]);
        }

        return $category;
    }
}
