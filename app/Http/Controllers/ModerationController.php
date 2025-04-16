<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ModerationLog;
use Illuminate\Http\Request;
use App\Models\Word;
use App\Models\Meaning;

class ModerationController extends Controller
{
    public function banUser(Request $request, User $user)
    {
        $threshold = env('MODERATION_REPUTATION_THRESHOLD', 500);

        // Only trusted users can perform this action
        if (auth()->user()->reputation < $threshold) {
            abort(403, 'You are not authorized to ban users.');
        }

        if ($user->is_banned) {
            return back()->with('warning', 'User is already banned.');
        }

        $user->is_banned = true;
        $user->save();

        // Log the action
        ModerationLog::create([
            'action_by' => auth()->id(),
            'target_user' => $user->id,
            'action' => 'ban_user',
            'reason' => $request->input('reason') ?? 'Banned by high-reputation user',
        ]);

        return back()->with('success', 'User has been banned.');
    }

    public function suspendWord(Request $request, Word $word)
    {
        if (auth()->user()->reputation < env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            abort(403);
        }

        $word->is_suspended = true;
        $word->save();

        ModerationLog::create([
            'action_by' => auth()->id(),
            'target_user' => $word->user_id,
            'word_id' => $word->id,
            'action' => 'suspend_word',
            'reason' => $request->input('reason') ?? 'Suspended by high-reputation user',
        ]);

        return back()->with('success', 'Word has been suspended.');
    }

    public function suspendMeaning(Request $request, Meaning $meaning)
    {
        if (auth()->user()->reputation < env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            abort(403);
        }

        $meaning->is_suspended = true;
        $meaning->save();

        ModerationLog::create([
            'action_by' => auth()->id(),
            'target_user' => $meaning->user_id,
            'word_id' => $meaning->word_id,
            'meaning_id' => $meaning->id,
            'action' => 'suspend_meaning',
            'reason' => $request->input('reason') ?? 'Suspended by high-reputation user',
        ]);

        return back()->with('success', 'Meaning has been suspended.');
    }

    public function unsuspendWord(Request $request, Word $word)
    {
        if (auth()->user()->reputation < env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            abort(403);
        }

        $word->is_suspended = false;
        $word->save();

        ModerationLog::create([
            'action_by' => auth()->id(),
            'word_id' => $word->id,
            'target_user' => $word->user_id,
            'action' => 'unsuspend_word',
            'reason' => $request->input('reason') ?? 'Unsuspended by moderator',
        ]);

        return back()->with('success', 'Word has been unsuspended.');
    }

    public function unsuspendMeaning(Request $request, Meaning $meaning)
    {
        if (auth()->user()->reputation < env('MODERATION_REPUTATION_THRESHOLD', 500)) {
            abort(403);
        }

        $meaning->is_suspended = false;
        $meaning->save();

        ModerationLog::create([
            'action_by' => auth()->id(),
            'meaning_id' => $meaning->id,
            'target_user' => $meaning->user_id,
            'word_id' => $meaning->word_id,
            'action' => 'unsuspend_meaning',
            'reason' => $request->input('reason') ?? 'Unsuspended by moderator',
        ]);

        return back()->with('success', 'Meaning has been unsuspended.');
    }

}
