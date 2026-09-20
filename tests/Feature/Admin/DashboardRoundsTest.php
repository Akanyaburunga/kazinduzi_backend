<?php

namespace Tests\Feature\Admin;

use App\Models\Joke;
use App\Models\JokeAttempt;
use App\Models\JokeSubmission;
use App\Models\Proverb;
use App\Models\ProverbAttempt;
use App\Models\ProverbSubmission;
use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\RiddleSubmission;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoundsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    public function test_dashboard_reports_per_mode_numbers(): void
    {
        $this->actingAs($this->admin());
        $cat = RiddleCategory::factory()->create();

        $riddle = Riddle::factory()->create(['category_id' => $cat->id]);
        $proverb = Proverb::factory()->create(['category_id' => $cat->id]);
        $jokeA = Joke::factory()->create(['category_id' => $cat->id]);
        $jokeB = Joke::factory()->create(['category_id' => $cat->id]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        RiddleAttempt::create(['user_id' => $userA->id, 'riddle_id' => $riddle->id, 'submitted_answer' => 'umusaruro', 'is_correct' => true, 'rewarded' => true]);
        RiddleAttempt::create(['user_id' => $userB->id, 'riddle_id' => $riddle->id, 'submitted_answer' => 'ikosa', 'is_correct' => false, 'rewarded' => false]);
        ProverbAttempt::create(['user_id' => $userA->id, 'proverb_id' => $proverb->id, 'submitted_answer' => 'umusaruro', 'is_correct' => true, 'rewarded' => true]);
        JokeAttempt::create(['user_id' => $userB->id, 'joke_id' => $jokeA->id, 'submitted_answer' => 'umusaruro', 'is_correct' => true, 'rewarded' => true]);
        JokeAttempt::create(['user_id' => $userB->id, 'joke_id' => $jokeB->id, 'submitted_answer' => 'ikosa', 'is_correct' => false, 'rewarded' => false]);

        $data = $this->getJson('/admin/api/dashboard')->assertOk()->json('data');

        $byMode = collect($data['by_mode'])->keyBy('mode');
        $this->assertSame(3, count($data['by_mode']));
        $this->assertSame(2, $byMode['sokwe']['attempts']);
        $this->assertSame(1, $byMode['sokwe']['solves']);
        $this->assertSame(1, $byMode['sokwe']['today_solves']);
        $this->assertSame(1, $byMode['hera']['attempts']);
        $this->assertSame(1, $byMode['hera']['solves']);
        $this->assertSame(2, $byMode['tuja']['attempts']);
        $this->assertSame(1, $byMode['tuja']['solves']);
        $this->assertSame(2, $byMode['tuja']['items']);
    }

    public function test_dashboard_reports_round_stats(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->create();

        Round::create([
            'user_id' => $user->id,
            'mode' => Round::MODE_SOKWE,
            'level' => 1,
            'item_count' => 10,
            'score' => 8,
            'current_streak' => 4,
            'best_streak' => 5,
            'status' => Round::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        Round::create([
            'user_id' => $user->id,
            'mode' => Round::MODE_TUJA,
            'level' => 1,
            'item_count' => 10,
            'score' => 2,
            'current_streak' => 1,
            'best_streak' => 2,
            'status' => Round::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $data = $this->getJson('/admin/api/dashboard')->assertOk()->json('data.round_stats');

        $this->assertSame(2, $data['total_rounds']);
        $this->assertSame(1, $data['active_rounds']);
        $this->assertSame(1, $data['completed_rounds']);
        $this->assertSame(2, $data['rounds_today']);
        $this->assertEquals(5.0, $data['avg_score']);
        $this->assertSame(2, $data['started_last_7d']);
        $this->assertSame(2, $data['started_last_30d']);
        $this->assertSame(1, $data['score_by_level'][0]['level']);
        $this->assertSame(2, $data['score_by_level'][0]['rounds']);
        $this->assertEquals(5.0, $data['score_by_level'][0]['avg_score']);
        $this->assertSame(1, $data['score_by_level'][0]['completed']);
        $this->assertSame(2, count($data['rounds_by_mode']));
    }

    public function test_performance_includes_per_mode_and_category_breakdowns(): void
    {
        $this->actingAs($this->admin());
        $cat = RiddleCategory::factory()->create();

        $riddle = Riddle::factory()->create(['category_id' => $cat->id]);
        $joke = Joke::factory()->create(['category_id' => $cat->id]);
        $user = User::factory()->create();

        RiddleAttempt::create(['user_id' => $user->id, 'riddle_id' => $riddle->id, 'submitted_answer' => 'umusaruro', 'is_correct' => true, 'rewarded' => true]);
        JokeAttempt::create(['user_id' => $user->id, 'joke_id' => $joke->id, 'submitted_answer' => 'umusaruro', 'is_correct' => true, 'rewarded' => true]);

        $data = $this->getJson('/admin/api/analytics/performance')->assertOk()->json('data');

        $byMode = collect($data['by_mode'])->keyBy('mode');
        $this->assertSame(3, count($data['by_mode']));
        $this->assertEquals(100.0, $byMode['sokwe']['success_rate']);
        $this->assertSame(1, $byMode['sokwe']['solves']);
        $this->assertEquals(100.0, $byMode['tuja']['success_rate']);

        $this->assertContains('sokwe', array_keys($data['category_by_mode']));
        $this->assertContains('hera', array_keys($data['category_by_mode']));
        $this->assertContains('tuja', array_keys($data['category_by_mode']));
        $this->assertSame($cat->name, $data['category_by_mode']['sokwe'][0]['name']);
        $this->assertSame($cat->name, $data['category_by_mode']['tuja'][0]['name']);
    }

    public function test_analytics_rounds_shape(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->create();

        Round::create([
            'user_id' => $user->id,
            'mode' => Round::MODE_SOKWE,
            'level' => 1,
            'item_count' => 10,
            'score' => 9,
            'current_streak' => 5,
            'best_streak' => 6,
            'status' => Round::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        Round::create([
            'user_id' => $user->id,
            'mode' => Round::MODE_HERA,
            'level' => 2,
            'item_count' => 10,
            'score' => 3,
            'current_streak' => 1,
            'best_streak' => 2,
            'status' => Round::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $data = $this->getJson('/admin/api/analytics/rounds?days=7')->assertOk()->json('data');

        $this->assertSame(7, count($data['daily_rounds']));
        $this->assertSame(2, $data['daily_rounds'][6]['rounds']);
        $this->assertSame(1, $data['daily_rounds'][6]['completed']);
        $this->assertSame(2, $data['total_rounds']);
        $this->assertSame(1, $data['completed_rounds']);
        $this->assertSame(1, $data['active_rounds']);
        $this->assertEquals(6.0, $data['avg_score']);
        $this->assertEquals(50.0, $data['completion_rate']);
        $this->assertEquals(50.0, $data['level_up_rate']);
        $this->assertSame(1, $data['level_up_rounds']);
        $this->assertSame(2, count($data['score_by_level']));
        $this->assertSame(1, $data['score_by_level'][0]['level']);
        $this->assertSame(2, count($data['rounds_by_mode']));
    }

    public function test_analytics_contributions_funnel(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->create();
        $cat = RiddleCategory::factory()->create();

        $submission = function ($class, string $status, array $extra = []) use ($user, $cat) {
            return $class::create(array_merge([
                'user_id' => $user->id,
                'category_id' => $cat->id,
                'source' => 'Test',
                'status' => $status,
            ], $extra));
        };

        $submission(RiddleSubmission::class, 'pending', ['question' => 'Inyoni', 'answer' => 'Inka']);
        $submission(RiddleSubmission::class, 'pending', ['question' => 'Igitaka', 'answer' => 'Iyoza']);
        $submission(RiddleSubmission::class, 'approved', ['question' => 'Igihe', 'answer' => 'Inyota']);
        $submission(ProverbSubmission::class, 'pending', ['question' => 'Umwibutsa', 'answer' => 'Inyota']);
        $submission(ProverbSubmission::class, 'rejected', ['question' => 'Imigani', 'answer' => 'Umunyamahoro']);
        $submission(JokeSubmission::class, 'approved', ['setup' => 'Urutoki', 'punchline' => 'Inka']);

        $data = $this->getJson('/admin/api/analytics/contributions')->assertOk()->json('data');

        $this->assertSame(2, $data['by_queue']['riddles']['pending']);
        $this->assertSame(1, $data['by_queue']['riddles']['approved']);
        $this->assertSame(1, $data['by_queue']['proverbs']['pending']);
        $this->assertSame(1, $data['by_queue']['proverbs']['rejected']);
        $this->assertSame(1, $data['by_queue']['jokes']['approved']);
        $this->assertSame(3, $data['totals']['pending']);
        $this->assertSame(2, $data['totals']['approved']);
        $this->assertSame(1, $data['totals']['rejected']);
    }
}