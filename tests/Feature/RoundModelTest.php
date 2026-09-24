<?php

namespace Tests\Feature;

use App\Models\Proverb;
use App\Models\Riddle;
use App\Models\Round;
use App\Models\RoundItem;
use App\Models\Joke;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_defaults_and_relations(): void
    {
        $user = User::factory()->create();
        $round = Round::factory()->create(['user_id' => $user->id]);

        $this->assertSame('sokwe', $round->mode);
        $this->assertSame(10, $round->item_count);
        $this->assertSame('active', $round->status);
        $this->assertFalse($round->isCompleted());
        $this->assertTrue($round->user->is($user));
    }

    public function test_round_completed_scope_and_state(): void
    {
        Round::factory()->completed()->create();
        $this->assertSame(0, Round::active()->count());

        Round::factory()->create();
        $this->assertSame(1, Round::active()->count());

        $done = Round::factory()->withScore(9, 10)->create();
        $this->assertTrue($done->isCompleted());
        $this->assertSame(9, $done->score);
    }

    public function test_round_items_ordered_by_position(): void
    {
        $round = Round::factory()->create();
        RoundItem::factory()->position(1)->create(['round_id' => $round->id]);
        RoundItem::factory()->position(0)->create(['round_id' => $round->id]);
        RoundItem::factory()->position(2)->create(['round_id' => $round->id]);

        $this->assertSame([0, 1, 2], $round->items->pluck('position')->all());
    }

    public function test_round_item_resolves_puzzle_model_by_type(): void
    {
        $riddle = Riddle::factory()->create();
        $proverb = Proverb::factory()->create();
        $joke = Joke::factory()->create();

        $ir = RoundItem::factory()->create(['puzzle_type' => 'riddle', 'puzzle_id' => $riddle->id]);
        $ip = RoundItem::factory()->create(['puzzle_type' => 'proverb', 'puzzle_id' => $proverb->id]);
        $ij = RoundItem::factory()->create(['puzzle_type' => 'joke', 'puzzle_id' => $joke->id]);

        $this->assertTrue($ir->puzzleModel()->is($riddle));
        $this->assertTrue($ip->puzzleModel()->is($proverb));
        $this->assertTrue($ij->puzzleModel()->is($joke));
    }
}
