<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Joke;
use Illuminate\Http\Request;

class JokeBulkController extends Controller
{
    /**
     * Apply an action to a set of jokes: suspend, unsuspend, delete,
     * restore, or change category.
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

        $jokes = $action === 'restore'
            ? Joke::onlyTrashed()->whereKey($ids)->get()
            : Joke::whereKey($ids)->get();

        foreach ($jokes as $joke) {
            match ($action) {
                'suspend' => $joke->update(['is_suspended' => true, 'suspended_reason' => $request->input('reason')]),
                'unsuspend' => $joke->update(['is_suspended' => false, 'suspended_reason' => null]),
                'delete' => $joke->delete(),
                'restore' => $joke->restore(),
                'change_category' => $joke->update(['category_id' => $request->input('category_id')]),
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
            'message' => "{$jokes->count()} jokes {$verb}.",
        ]);
    }
}