<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ModerationLog;
use Illuminate\Http\Request;

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
}
