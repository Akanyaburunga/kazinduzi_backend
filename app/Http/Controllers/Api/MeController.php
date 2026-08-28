<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Meaning;
use App\Models\Word;
use App\Support\Achievements;
use App\Support\Levels;
use App\Support\Streaks;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Authenticated user profile, points, level and activity stats.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $profilePictureUrl = $user->profile_picture
            ? asset('storage/' . $user->profile_picture)
            : asset('images/default-profile.png');

        $attempts = $user->riddleAttempts();

        $stats = [
            'words_contributed' => $user->words()->count(),
            'meanings_contributed' => $user->meanings()->count(),
            'riddles_solved' => (clone $attempts)->where('is_correct', true)->count(),
            'riddle_attempts' => (clone $attempts)->count(),
            'correct_riddle_attempts' => (clone $attempts)->where('is_correct', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'profile_picture_url' => $profilePictureUrl,
                'referral_code' => $user->referral_code,
                'points' => [
                    'reputation' => (int) $user->reputation,
                    'level' => Levels::currentLevel((int) $user->reputation),
                ],
                'streak' => [
                    'current' => (int) $user->current_streak,
                    'longest' => (int) $user->longest_streak,
                ],
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Level ladder: current level, progress and the full threshold table.
     */
    public function levels(Request $request)
    {
        $user = $request->user();
        $reputation = (int) $user->reputation;

        return response()->json([
            'success' => true,
            'data' => [
                'current' => Levels::currentLevel($reputation),
                'levels' => collect(Levels::THRESHOLDS)->map(fn ($minimum, $level) => [
                    'level' => $level,
                    'title' => Levels::titleForLevel($level),
                    'min_reputation' => $minimum,
                ])->values(),
            ],
        ]);
    }

    /**
     * Badge catalogue with each user's earn/progress state.
     */
    public function achievements(Request $request)
    {
        $user = $request->user();

        $earned = $user->achievements()
            ->get()
            ->keyBy('id')
            ->mapWithKeys(fn ($achievement) => [
                $achievement->id => $achievement->pivot->unlocked_at,
            ]);

        $badges = Achievements::catalogue()->map(function (Achievement $achievement) use ($user, $earned) {
            $progress = Achievements::progress($user, $achievement->metric);
            $unlockedAt = $earned->get($achievement->id);

            return [
                'id' => $achievement->id,
                'slug' => $achievement->slug,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'category' => $achievement->category,
                'icon' => $achievement->icon,
                'threshold' => $achievement->threshold,
                'metric' => $achievement->metric,
                'earned' => $unlockedAt !== null,
                'earned_at' => $unlockedAt,
                'progress' => $progress,
                'goal' => $achievement->threshold,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'earned_count' => $earned->count(),
                'total' => $badges->count(),
                'achievements' => $badges->values(),
            ],
        ]);
    }

    /**
     * Single home-screen payload: points, level, streak, badges, favorites
     * and activity counts combined for the Android client.
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $attempts = $user->riddleAttempts();
        $total = (clone $attempts)->count();
        $solved = (clone $attempts)->where('is_correct', true)->count();
        $streaks = Streaks::compute($user);

        $profilePictureUrl = $user->profile_picture
            ? asset('storage/' . $user->profile_picture)
            : asset('images/default-profile.png');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_picture_url' => $profilePictureUrl,
                ],
                'points' => [
                    'reputation' => (int) $user->reputation,
                    'level' => Levels::currentLevel((int) $user->reputation),
                ],
                'streak' => [
                    'current' => $streaks['current'],
                    'longest' => $streaks['longest'],
                ],
                'badges' => [
                    'earned_count' => $user->achievements()->count(),
                    'total' => Achievements::catalogue()->count(),
                    'earned_slugs' => $user->achievements()->pluck('achievements.slug'),
                ],
                'favorites_count' => $user->favoriteRiddles()->count(),
                'activity' => [
                    'total_attempts' => $total,
                    'riddles_solved' => $solved,
                    'accuracy' => $total > 0 ? round(($solved / $total) * 100, 1) : 0,
                    'unique_riddles' => (clone $attempts)->distinct('riddle_id')->count('riddle_id'),
                    'submissions_count' => $user->riddleSubmissions()->count(),
                    'shares_count' => $user->shares()->count(),
                ],
            ],
        ]);
    }
}
