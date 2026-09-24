<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    /**
     * Points ledger: current total plus a paginated history of reputation changes.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $logs = $user->reputationLogs()
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($log) => [
                'id' => $log->id,
                'change' => (int) $log->change,
                'reason' => $log->reason,
                'related_type' => $log->related_type,
                'related_id' => $log->related_id,
                'created_at' => $log->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (int) $user->reputation,
                'history' => $logs,
            ],
        ]);
    }
}
