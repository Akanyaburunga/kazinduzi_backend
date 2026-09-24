<?php

namespace Tests\Feature\Api;

use App\Models\Joke;
use App\Models\Proverb;
use App\Models\Riddle;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function makeJokes(int $count): void
    {
        Joke::factory()->count($count)->create([
            'punchline' => fn () => 'punchline-'.uniqid(),
            'distractors' => ['anya', 'imbwa', 'inzoka'],
        ]);
    }

    private function makeRiddles(int $count): void
    {
        Riddle::factory()->count($count)->create([
            'answer' => 'inkoko-'.fake()->unique()->numberBetween(1, 99999),
        ]);
    }

    private function start(User $user, string $mode, int $level = 1): array
    {
        return $this->actingAs($user)
            ->postJson("/api/games/{$mode}/rounds", ['level' => $level])
            ->assertOk()
            ->json('data');
    }

    private function poolIds(int $roundId): array
    {
        return RoundItem::where('round_id', $roundId)->orderBy('position')->pluck('puzzle_id')->all();
    }

    public function test_recency_window_excludes_recently_delivered_jokes_when_fresh_content_exists(): void
    {
        $user = $this->user();
        $this->makeJokes(20);

        $first = $this->start($user, 'tuja');
        $firstIds = $this->poolIds($first['round']['id']);

        $this->assertCount(10, $firstIds);

        $second = $this->start($user, 'tuja');
        $secondIds = $this->poolIds($second['round']['id']);

        $this->assertCount(10, $secondIds);
        $this->assertSame(0, count(array_intersect($firstIds, $secondIds)));
    }

    public function test_recency_window_still_delivers_a_full_round_when_fresh_content_is_short(): void
    {
        $user = $this->user();
        $this->makeJokes(15);

        $first = $this->start($user, 'tuja');
        $this->poolIds($first['round']['id']);

        $second = $this->start($user, 'tuja');

        $this->assertSame(10, $second['round']['item_count']);
        $this->assertCount(10, $this->poolIds($second['round']['id']));
    }

    public function test_shuffled_order_is_stable_for_same_user_day_and_level(): void
    {
        config(['riddles.round_recency_rounds' => 0]);

        $user = $this->user();
        $this->makeRiddles(30);

        $a = $this->start($user, 'sokwe');
        $b = $this->start($user, 'sokwe', 1);

        $this->assertSame($this->poolIds($a['round']['id']), $this->poolIds($b['round']['id']));
    }

    public function test_shuffled_order_changes_with_the_user(): void
    {
        config(['riddles.round_recency_rounds' => 0]);

        $alice = $this->user();
        $bob = $this->user();
        $this->makeRiddles(30);

        $a = $this->start($alice, 'sokwe');
        $b = $this->start($bob, 'sokwe');

        $this->assertNotSame($this->poolIds($a['round']['id']), $this->poolIds($b['round']['id']));
    }

    public function test_shuffled_order_changes_with_the_day(): void
    {
        config(['riddles.round_recency_rounds' => 0]);

        $user = $this->user();
        $this->makeRiddles(30);

        Carbon::setTestNow('2026-09-22 08:00:00');
        $a = $this->start($user, 'sokwe');

        Carbon::setTestNow('2026-09-23 08:00:00');
        $b = $this->start($user, 'sokwe');

        Carbon::setTestNow();

        $this->assertNotSame($this->poolIds($a['round']['id']), $this->poolIds($b['round']['id']));
    }

    public function test_joke_options_never_reuse_the_rounds_other_punchline(): void
    {
        $user = $this->user();
        $this->makeJokes(10);

        $data = $this->start($user, 'tuja');

        foreach ($data['round']['item_count'] === 10 ? [$data['item']] : [] as $item) {
            $this->assertSame('joke', $item['type']);
            $this->assertCount(4, $item['options']);
        }

        $round = Round::findOrFail($data['round']['id']);

        foreach ($round->items as $item) {
            $joke = $item->puzzleModel();
            $payload = $this->actingAs($user)
                ->getJson("/api/games/tuja/rounds/{$round->id}/items/{$item->position}")
                ->assertOk()
                ->json('data.item');

            $siblingPunchlines = $round->items
                ->filter(fn ($s) => $s->id !== $item->id)
                ->map(fn ($s) => $s->puzzleModel()->punchline)
                ->values();

            foreach ($siblingPunchlines as $punchline) {
                $this->assertNotContains($punchline, $payload['options']);
            }

            $this->assertContains($joke->punchline, $payload['options']);
        }
    }
}