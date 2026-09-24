<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use Illuminate\Http\Request;

class RiddleBulkController extends Controller
{
    /**
     * Apply an action to a set of riddles: suspend, unsuspend, delete, restore,
     * or change category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:suspend,unsuspend,delete,restore,change_category',
            'category_id' => 'required_if:action,change_category|nullable|integer|exists:riddle_categories,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        $riddles = $action === 'restore'
            ? Riddle::onlyTrashed()->whereKey($ids)->get()
            : Riddle::whereKey($ids)->get();

        foreach ($riddles as $riddle) {
            match ($action) {
                'suspend' => $riddle->update(['is_suspended' => true, 'suspended_reason' => $request->input('reason')]),
                'unsuspend' => $riddle->update(['is_suspended' => false, 'suspended_reason' => null]),
                'delete' => $riddle->delete(),
                'restore' => $riddle->restore(),
                'change_category' => $riddle->update(['category_id' => $request->input('category_id')]),
                default => true,
            };
        }

        $verb = match ($action) {
            'suspend' => 'suspended',
            'unsuspend' => 'unsuspended',
            'delete' => 'deleted',
            'restore' => 'restored',
            'change_category' => 'moved to the selected category',
            default => 'updated',
        };

        return response()->json([
            'success' => true,
            'message' => "{$riddles->count()} riddles {$verb}.",
        ]);
    }
}
