<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleShare;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    /**
     * Create a shareable short link + invitation record for a riddle
     * ("send to friends" / challenge-a-friend via a public link).
     */
    public function store(Request $request, Riddle $riddle)
    {
        if ($riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Riddle not available.'], 404);
        }

        $request->validate([
            'recipient_email' => ['nullable', 'email', 'max:255'],
        ]);

        $share = RiddleShare::create([
            'user_id' => $request->user()->id,
            'riddle_id' => $riddle->id,
            'code' => Str::random(12),
            'recipient_email' => $request->input('recipient_email'),
        ]);

        $url = route('api.riddles.share.show', ['code' => $share->code]);

        return response()->json([
            'success' => true,
            'data' => [
                'share_url' => $url,
                'code' => $share->code,
                'riddle_id' => $riddle->id,
                'recipient_email' => $share->recipient_email,
            ],
        ], 201);
    }

    /**
     * Resolve a share link: reveal the riddle (answer omitted) and count the view.
     */
    public function show(string $code)
    {
        $share = RiddleShare::where('code', $code)->first();

        if (! $share || $share->riddle->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Share not found.'], 404);
        }

        $share->increment('views');

        $riddle = $share->riddle->load('category:id,name,slug', 'tags:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => [
                'shared_by' => $share->user_id,
                'riddle' => $this->gamePayload($riddle),
            ],
        ]);
    }

    /**
     * Game-facing payload (answer omitted).
     */
    protected function gamePayload(Riddle $riddle): array
    {
        return [
            'id' => $riddle->id,
            'category' => $riddle->category
                ? ['id' => $riddle->category->id, 'name' => $riddle->category->name, 'slug' => $riddle->category->slug]
                : null,
            'question' => $riddle->question,
            'difficulty' => $riddle->difficulty,
            'riddle_type' => $riddle->riddle_type,
            'tags' => $riddle->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'hint' => $riddle->hint,
            'hint2' => $riddle->hint2,
            'created_at' => $riddle->created_at,
        ];
    }
}
