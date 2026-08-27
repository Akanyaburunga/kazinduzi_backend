<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;

class DashboardController extends Controller
{
    /**
     * Aggregate stats for the admin dashboard.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_riddles' => Riddle::count(),
                'suspended_riddles' => Riddle::where('is_suspended', true)->count(),
                'total_categories' => RiddleCategory::count(),
                'total_attempts' => RiddleAttempt::count(),
                'correct_attempts' => RiddleAttempt::where('is_correct', true)->count(),
            ],
        ]);
    }
}
