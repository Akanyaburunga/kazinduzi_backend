<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\RiddleHintUse;
use App\Models\RiddleShare;
use App\Models\RiddleSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    /**
     * Believable demo users + activity so the API (leaderboard, dashboard,
     * analytics, achievements, shares, submissions and duels) can be exercised
     * end-to-end against seeded data.
     */
    public function run(): void
    {
        $riddles = Riddle::query()->where('is_suspended', false)->get();

        $definitions = [
            [
                'name' => 'Nyandwi',
                'email' => 'nyandwi@example.com',
                'password' => 'password',
                'reputation' => 1240,
                'longest_streak' => 21,
                'current_streak' => 7,
                'attempts' => 12,
                'favorites' => 4,
                'solves_without_hint' => 6,
                'shares' => 2,
            ],
            [
                'name' => 'Umwizerwa',
                'email' => 'umwizerwa@example.com',
                'password' => 'password',
                'reputation' => 760,
                'longest_streak' => 12,
                'current_streak' => 3,
                'attempts' => 9,
                'favorites' => 3,
                'solves_without_hint' => 5,
                'shares' => 1,
            ],
            [
                'name' => 'Gakwaya',
                'email' => 'gakwaya@example.com',
                'password' => 'password',
                'reputation' => 405,
                'longest_streak' => 8,
                'current_streak' => 2,
                'attempts' => 7,
                'favorites' => 2,
                'solves_without_hint' => 3,
                'shares' => 1,
            ],
            [
                'name' => 'Niyonkuru',
                'email' => 'niyonkuru@example.com',
                'password' => 'password',
                'reputation' => 85,
                'longest_streak' => 4,
                'current_streak' => 1,
                'attempts' => 4,
                'favorites' => 1,
                'solves_without_hint' => 1,
                'shares' => 0,
            ],
        ];

        foreach ($definitions as $index => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                    'current_streak' => $data['current_streak'],
                    'longest_streak' => $data['longest_streak'],
                ]
            );

            // reputation is not mass-assignable — set it directly to keep the
            // leaderboard and levels coherent with the seeded activity below.
            $user->reputation = $data['reputation'];
            $user->save();

            $this->seedAttempts($user, $riddles, $data['attempts'], $data['solves_without_hint']);
            $this->seedReputationLog($user, $data['reputation'], $index);
            $this->seedFavorites($user, $riddles, $data['favorites'], $index);
            $this->seedShares($user, $riddles, $data['shares'], $index);
        }

        $this->seedRiddleSubmissions();
        $this->seedPendingDuel();
    }

    /**
     * Create a sensible set of riddle attempts (unique user+riddle constraint)
     * and reputation-log entries that match each user's total reputation.
     */
    protected function seedAttempts(User $user, $riddles, int $count, int $solvesWithoutHint): void
    {
        $pool = $riddles->shuffle()->take($count);

        foreach ($pool as $i => $riddle) {
            $this->attemptIfUnique($user, $riddle, true, 'seed');

            // A handful of solves are "no hint" (no RiddleHintUse row), the rest
            // request a hint so the no_hint badge metrics stay interesting.
            if ($i >= $solvesWithoutHint) {
                RiddleHintUse::firstOrCreate(
                    ['user_id' => $user->id, 'riddle_id' => $riddle->id],
                    ['count' => 1]
                );
            }
        }
    }

    /**
     * Insert a RiddleAttempt unless the (user, riddle) pair already exists.
     */
    protected function attemptIfUnique(User $user, Riddle $riddle, bool $correct, string $answer): void
    {
        if (RiddleAttempt::where('user_id', $user->id)->where('riddle_id', $riddle->id)->exists()) {
            return;
        }

        RiddleAttempt::create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'submitted_answer' => $correct ? $riddle->answer : 'seed-miss',
            'is_correct' => $correct,
            'rewarded' => $correct,
        ]);
    }

    /**
     * Create reputation-log rows whose sum equals the intended total so the
     * leaderboard period aggregation reconciles with the user's reputation.
     */
    protected function seedReputationLog(User $user, int $total, int $seed): void
    {
        $dailyCap = (int) config('riddles.daily_solve_reputation', 5) ?: 5;
        $remaining = $total;
        $day = 0;

        while ($remaining > 0) {
            $chunk = min($dailyCap, $remaining);
            $created = now()->subDays($seed * 14 + $day)->subHours($day % 12);

            if (! $user->reputationLogs()->where('change', $chunk)->whereDate('created_at', $created)->exists()) {
                $user->reputationLogs()->create([
                    'change' => $chunk,
                    'reason' => 'Solved a riddle',
                    'related_type' => RiddleAttempt::class,
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);
            }

            $remaining -= $chunk;
            $day++;
        }
    }

    /**
     * Bookmark (favorite) a handful of riddles for the user.
     */
    protected function seedFavorites(User $user, $riddles, int $count, int $seed): void
    {
        $pool = $riddles->shuffle()->take($count);
        $user->favoriteRiddles()->syncWithoutDetaching(
            $pool->pluck('id')->mapWithKeys(fn ($id) => [$id => ['created_at' => now()->subDays($seed), 'updated_at' => now()->subDays($seed)]])
        );
    }

    /**
     * Create share/invite records for the user.
     */
    protected function seedShares(User $user, $riddles, int $count, int $seed): void
    {
        if ($count <= 0 || $riddles->isEmpty()) {
            return;
        }

        $pool = $riddles->shuffle()->take($count);

        foreach ($pool as $riddle) {
            $code = strtoupper(Str::random(8));

            RiddleShare::firstOrCreate(
                ['code' => $code],
                [
                    'user_id' => $user->id,
                    'riddle_id' => $riddle->id,
                    'views' => random_int(0, 40),
                    'created_at' => now()->subDays($seed),
                    'updated_at' => now()->subDays($seed),
                ]
            );
        }
    }

    /**
     * A mix of pending/approved/rejected riddle submissions so the admin
     * moderation queue and the submitter's history have content.
     */
    protected function seedRiddleSubmissions(): void
    {
        $category = RiddleCategory::query()->first();
        $submitter = User::where('email', 'umwizerwa@example.com')->first();

        if (! $submitter) {
            return;
        }

        $definitions = [
            [
                'question' => 'Ikira iyo cyaguye ku musi, kikaguma kitonya',
                'answer' => 'uruyovu',
                'difficulty' => 'medium',
                'riddle_type' => 'what_is_it',
                'hint' => 'Kiba hasi.',
                'hint2' => 'Abana bagikina.',
                'status' => RiddleSubmission::STATUS_PENDING,
            ],
            [
                'question' => 'Mbere ya mbere na nyuma, ihimba amazi nyabibumba',
                'answer' => 'urugo',
                'difficulty' => 'easy',
                'riddle_type' => 'riddle',
                'hint' => 'Aho buri muntu aba.',
                'hint2' => 'Urakinamo wese.',
                'status' => RiddleSubmission::STATUS_PENDING,
            ],
        ];

        foreach ($definitions as $data) {
            RiddleSubmission::firstOrCreate(
                ['question' => $data['question']],
                [
                    'user_id' => $submitter->id,
                    'category_id' => $category ? $category->id : null,
                    'answer' => $data['answer'],
                    'difficulty' => $data['difficulty'],
                    'riddle_type' => $data['riddle_type'],
                    'hint' => $data['hint'],
                    'hint2' => $data['hint2'],
                    'source' => 'Kazinduzi intsinzi',
                    'status' => $data['status'],
                ]
            );
        }
    }

    /**
     * A pending duel between two of the demo users so duels C7 can be tested
     * from a freshly seeded database (opponent sees it in pending_challenges).
     */
    protected function seedPendingDuel(): void
    {
        $initiator = User::where('email', 'nyandwi@example.com')->first();
        $opponent = User::where('email', 'umwizerwa@example.com')->first();
        $riddle = Riddle::query()->where('is_suspended', false)->first();

        if (! $initiator || ! $opponent || ! $riddle) {
            return;
        }

        Challenge::updateOrCreate(
            ['initiator_id' => $initiator->id, 'opponent_id' => $opponent->id, 'riddle_id' => $riddle->id],
            [
                'wager' => min(10, config('riddles.duel_max_wager', 20)),
                'status' => Challenge::STATUS_PENDING,
            ]
        );
    }
}
