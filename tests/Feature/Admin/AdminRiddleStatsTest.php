<?php

namespace Tests\Feature\Admin;

use App\Models\Riddle;
use App\Models\RiddleAttempt;
use App\Models\RiddleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRiddleStatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function category(): RiddleCategory
    {
        return RiddleCategory::factory()->create();
    }

    public function test_riddle_stats_payload(): void
    {
        $this->actingAs($this->admin());
        $riddle = Riddle::factory()->create(['category_id' => $this->category()->id]);

        $this->makeAttempt(User::factory()->create(), $riddle, true, now()->subDays(2));
        $this->makeAttempt(User::factory()->create(), $riddle, false, now()->subDays(1), 'not even close');
        $this->makeAttempt(User::factory()->create(), $riddle, false, now()->subDays(1), 'hmm');

        $data = $this->getJson("/admin/api/riddles/{$riddle->id}/stats")->assertOk()->json('data');

        $this->assertSame(3, $data['attempts_total']);
        $this->assertSame(1, $data['solved_count']);
        $this->assertEquals(33.3, $data['success_rate']);
        $this->assertSame($riddle->id, $data['riddle']['id']);
        $this->assertSame(2, count($data['attempts_by_day']));
        $this->assertSame(2, count($data['wrong_answers']));
        $this->assertSame('not even close', $data['wrong_answers'][0]['answer']);
    }

    public function test_zero_attempts_reports_zero_success(): void
    {
        $this->actingAs($this->admin());
        $riddle = Riddle::factory()->create(['category_id' => $this->category()->id]);

        $data = $this->getJson("/admin/api/riddles/{$riddle->id}/stats")->assertOk()->json('data');

        $this->assertSame(0, $data['attempts_total']);
        $this->assertSame(0, $data['solved_count']);
        $this->assertSame(0, $data['success_rate']);
    }

    public function test_export_generates_csv(): void
    {
        $this->actingAs($this->admin());
        $riddle = Riddle::factory()->create([
            'category_id' => $this->category()->id,
            'question' => 'Umuseke waragiye?',
            'difficulty' => 'easy',
        ]);

        $response = $this->get('/admin/api/riddles/export');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('filename=', $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Umuseke waragiye?', $content);
        $this->assertStringContainsString((string) $riddle->id, $content);
    }

    public function test_export_respects_status_filter(): void
    {
        $this->actingAs($this->admin());
        $cat = $this->category();
        Riddle::factory()->create(['category_id' => $cat->id, 'is_suspended' => true]);
        $active = Riddle::factory()->create(['category_id' => $cat->id, 'is_suspended' => false]);

        $content = $this->get('/admin/api/riddles/export?status=active')->assertOk()->streamedContent();
        $this->assertStringContainsString($active->question, $content);
    }

    public function test_stats_requires_admin(): void
    {
        $this->actingAs(User::factory()->create(['reputation' => 0]));
        $riddle = Riddle::factory()->create(['category_id' => $this->category()->id]);

        $this->getJson("/admin/api/riddles/{$riddle->id}/stats")->assertForbidden();
        $this->get('/admin/api/riddles/export')->assertForbidden();
    }

    private function makeAttempt(User $user, Riddle $riddle, bool $correct, $createdAt, ?string $answer = null): RiddleAttempt
    {
        $attempt = RiddleAttempt::create([
            'user_id' => $user->id,
            'riddle_id' => $riddle->id,
            'submitted_answer' => $answer ?? ($correct ? 'correct' : 'wrong'),
            'is_correct' => $correct,
            'rewarded' => $correct,
        ]);

        $attempt->created_at = $createdAt;
        $attempt->save();

        return $attempt;
    }
}
