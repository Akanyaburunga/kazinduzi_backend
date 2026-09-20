<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proverb;
use Illuminate\Http\Request;

class ProverbBulkController extends Controller
{
    /**
     * Apply an action to a set of proverbs: suspend, unsuspend, delete,
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

        $proverbs = $action === 'restore'
            ? Proverb::onlyTrashed()->whereKey($ids)->get()
            : Proverb::whereKey($ids)->get();

        foreach ($proverbs as $proverb) {
            match ($action) {
                'suspend' => $proverb->update(['is_suspended' => true, 'suspended_reason' => $request->input('reason')]),
                'unsuspend' => $proverb->update(['is_suspended' => false, 'suspended_reason' => null]),
                'delete' => $proverb->delete(),
                'restore' => $proverb->restore(),
                'change_category' => $proverb->update(['category_id' => $request->input('category_id')]),
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
            'message' => "{$proverbs->count()} proverbs {$verb}.",
        ]);
    }
}