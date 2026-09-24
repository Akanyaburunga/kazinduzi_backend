<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\Proverb;
use App\Models\Riddle;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\RiddleAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoundTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function makeRiddles(int $count): void
    {
        Riddle::factory()->count($count)->create(['answer' => 'inkoko']);
    }

    private function makeProverbs(int $count): void
    {
        Proverb::factory()->count($count)->create(['answer' => 'ntiwigira inama']);
    }

    private function makeJokes(int $count): void
    {
        Joke::factory()->count($count)->create([
            'punchline' => 'punchline',
            'distractors' => ['anya', 'imbwa', 'inzoka'],
        ]);
    }

    private function startRound(User $user, string $mode, array $payload = []): array
    {
        return $this->actingAs($user)
            ->postJson("/api/games/{$mode}/rounds", $payload)
            ->assertOk()
            ->json('data');
    }

    /**
     * Correct answer string for the item at a given position.
     */
    private function answerFor(Round $round, int $position): string
    {
        $item = $round->items->firstWhere('position', $position);
        $puzzle = $item->puzzleModel();

        return $item->puzzle_type === RoundItem::PUZZLE_JOKE ? $puzzle->punchline : $puzzle->answer;
    }

    public function test_unauthenticated_start_is_rejected(): void
    {
        $this->postJson('/api/games/sokwe/rounds')->assertStatus(401);
    }

    public function test_invalid_mode_is_not_routed(): void
    {
        $this->actingAs($this->user())->postJson('/api/games/notamode/rounds')->assertStatus(404);
    }

    public function test_start_returns_ten_items_first_only_and_no_answer_leak(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $data = $this->startRound($user, 'sokwe');

        $this->assertSame(10, $data['round']['item_count']);
        $this->assertSame(0, $data['round']['index']);
        $this->assertSame(0, $data['round']['score']);
        $this->assertArrayHasKey('item', $data);
        $this->assertArrayNotHasKey('answer', $data['item']);

        $this->assertSame(1, Round::count());
        $this->assertSame(10, RoundItem::count());
    }

    public function test_round_items_are_unsolved_and_from_the_correct_mode(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);
        $this->makeProverbs(5);

        // The user already solved 3 riddles elsewhere -> only 7 remain unsolved.
        $solved = Riddle::limit(3)->get();
        foreach ($solved as $riddle) {
            RiddleAttempt::factory()->correct()->create([
                'user_id' => $user->id,
                'riddle_id' => $riddle->id,
            ]);
        }

        $data = $this->startRound($user, 'sokwe');

        $this->assertSame(7, $data['round']['item_count']);
        $this->assertSame('riddle', $data['item']['type']);

        $this->assertSame(0, RoundItem::where('puzzle_type', 'proverb')->count());
        $this->assertSame(7, RoundItem::where('puzzle_type', 'riddle')->count());
    }

    public function test_correct_answer_increments_score_and_streak(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];
        $round = Round::findOrFail($roundId);

        // Wrong answer: no advance, item stays pending.
        $first = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => 'amagara'])
            ->assertOk()
            ->json();

        $this->assertFalse($first['correct']);
        $this->assertSame(0, $first['round']['score']);
        $this->assertSame(0, $first['round']['index']);
        $this->assertSame('pending', $round->items->firstWhere('position', 0)->status);
        $this->assertSame(1, $round->items->firstWhere('position', 0)->attempts);

        // Correct answer: score + streak + best_streak, advance to position 1.
        $second = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $this->answerFor($round, 0)])
            ->assertOk()
            ->json();

        $this->assertTrue($second['correct']);
        $this->assertSame(1, $second['round']['score']);
        $this->assertSame(1, $second['round']['index']);
        $this->assertSame(1, $second['round']['current_streak']);
        $this->assertSame(1, $second['round']['best_streak']);
        $this->assertSame('solved', $round->refresh()->items->firstWhere('position', 0)->status);
    }

    public function test_concede_reveals_answer_resets_streak_and_marks_conceded(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];
        $round = Round::findOrFail($roundId);

        // Solve position 0 first (streak 1), then concede position 1.
        $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/answer", ['answer' => $this->answerFor($round, 0)])
            ->assertOk();

        $response = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/1/answer", ['answer' => 'ndaguhaye'])
            ->assertOk()
            ->json();

        $this->assertTrue($response['conceded']);
        $this->assertFalse($response['correct']);
        $this->assertSame($this->answerFor($round, 1), $response['answer']);
        $this->assertSame(0, $response['round']['current_streak']);
        $this->assertSame('conceded', $round->refresh()->items->firstWhere('position', 1)->status);
    }

    public function test_skip_behaves_like_concede(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];
        $round = Round::findOrFail($roundId);

        $response = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/items/0/skip")
            ->assertOk()
            ->json();

        $this->assertTrue($response['conceded']);
        $this->assertSame($this->answerFor($round, 0), $response['answer']);
        $this->assertSame(0, $response['round']['current_streak']);
        $this->assertSame('conceded', $round->refresh()->items->firstWhere('position', 0)->status);
    }

    public function test_resume_returns_current_item_then_null_when_completed(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];

        $resume = $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}")
            ->assertOk()
            ->json('data');

        $this->assertNotNull($resume['item']);
        $this->assertSame(0, $resume['item']['position']);

        // Play every item through to completion.
        for ($i = 0; $i < 10; $i++) {
            $res = $this->actingAs($user)
                ->postJson("/api/games/sokwe/rounds/{$roundId}/items/{$i}/answer", ['answer' => $this->answerFor(Round::findOrFail($roundId), $i)])
                ->assertOk()
                ->json();
            $this->assertNotEmpty($res);
        }

        $finished = $this->actingAs($user)
            ->getJson("/api/games/sokwe/rounds/{$roundId}")
            ->assertOk()
            ->json('data');

        $this->assertTrue($finished['round']['completed']);
        $this->assertNull($finished['item']);
    }

    public function test_complete_reports_performance_and_tier_state(): void
    {
        $user = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->postJson("/api/games/sokwe/rounds/{$roundId}/items/{$i}/answer", ['answer' => $this->answerFor(Round::findOrFail($roundId), $i)])
                ->assertOk();
        }

        $complete = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/complete")
            ->assertOk()
            ->json('data');

        $this->assertSame('top', $complete['performance']);
        $this->assertSame(10, $complete['round']['score']);
        $this->assertTrue($complete['round']['completed']);
        $this->assertNull($complete['round']['next_level']); // only 10 templates: no harder tier
    }

    public function test_level_available_when_score_qualifies_and_harder_tier_exists(): void
    {
        $user = $this->user();
        $this->makeRiddles(30);

        $roundId = $this->startRound($user, 'sokwe')['round']['id'];
        $round = Round::findOrFail($roundId);

        // Solve 8 correctly -> score 8 qualifies for a harder tier (pool of 30 has room).
        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($user)
                ->postJson("/api/games/sokwe/rounds/{$roundId}/items/{$i}/answer", ['answer' => $this->answerFor(Round::findOrFail($roundId), $i)])
                ->assertOk();
        }

        $complete = $this->actingAs($user)
            ->postJson("/api/games/sokwe/rounds/{$roundId}/complete")
            ->assertOk()
            ->json('data');

        $this->assertSame(8, $complete['round']['score']);
        $this->assertTrue($complete['round']['has_more_levels']);
        $this->assertSame(2, $complete['round']['next_level']);
        $this->assertTrue($complete['round']['level_available']);
    }

    public function test_joke_round_serves_options_and_correct_pick_scores(): void
    {
        $user = $this->user();
        $this->makeJokes(10);

        $data = $this->startRound($user, 'tuja');

        $this->assertSame('joke', $data['item']['type']);
        $this->assertCount(4, $data['item']['options']);
        $this->assertArrayNotHasKey('punchline', $data['item']);

        $roundId = $data['round']['id'];
        $punchline = $this->answerFor(Round::findOrFail($roundId), 0);

        $response = $this->actingAs($user)
            ->postJson("/api/games/tuja/rounds/{$roundId}/items/0/answer", ['option' => $punchline])
            ->assertOk()
            ->json();

        $this->assertTrue($response['correct']);
        $this->assertSame(1, $response['round']['score']);
        $this->assertSame(1, $response['round']['current_streak']);
    }

    public function test_proverb_round_uses_proverb_matcher(): void
    {
        $user = $this->user();
        $this->makeProverbs(10);

        $data = $this->startRound($user, 'hera');

        $this->assertSame('proverb', $data['item']['type']);

        $roundId = $data['round']['id'];
        $answer = $this->answerFor(Round::findOrFail($roundId), 0);

        $response = $this->actingAs($user)
            ->postJson("/api/games/hera/rounds/{$roundId}/items/0/answer", ['answer' => $answer])
            ->assertOk()
            ->json();

        $this->assertTrue($response['correct']);
        $this->assertSame(1, $response['round']['score']);
    }

    public function test_another_users_round_is_forbidden(): void
    {
        $owner = $this->user();
        $this->makeRiddles(10);

        $roundId = $this->startRound($owner, 'sokwe')['round']['id'];

        $this->actingAs($this->user())
            ->getJson("/api/games/sokwe/rounds/{$roundId}")
            ->assertStatus(403);
    }
}
