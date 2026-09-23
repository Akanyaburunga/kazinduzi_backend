<?php

namespace Tests\Feature;

use App\Models\Joke;
use App\Models\Proverb;
use App\Models\Riddle;
use App\Support\RinjoraData;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeding must add, never destroy: running DatabaseSeeder against an
 * already-seeded database must not mutate existing rows, overwrite admin
 * suspensions, reset timestamps, or change counts.
 */
class SeedersNonDestructiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_idempotent_on_counts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(RinjoraData::sokwe()), Riddle::count());
        $this->assertSame(count(RinjoraData::heraheza()), Proverb::count());
        $this->assertSame(count(RinjoraData::tujajure()), Joke::count());
    }

    public function test_riddle_seed_does_not_undo_admin_edits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $riddle = Riddle::first();
        $oldQuestion = $riddle->question;
        $oldCreatedAt = $riddle->created_at;
        $oldAnswer = $riddle->answer;

        // Simulate admin moderation after the initial seed.
        $riddle->forceFill([
            'answer' => 'Answe modifie par admin',
            'difficulty' => 'hard',
            'is_suspended' => true,
            'suspended_reason' => 'Admin probe',
            'updated_at' => now()->addDays(10),
        ])->save();

        $this->seed(DatabaseSeeder::class);

        $fresh = Riddle::where('question', $oldQuestion)->first();

        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_suspended);
        $this->assertSame('Answe modifie par admin', $fresh->answer);
        $this->assertSame('hard', $fresh->difficulty);
        $this->assertSame('Admin probe', $fresh->suspended_reason);
        $this->assertSame($oldCreatedAt->toDateTimeString(), $fresh->created_at->toDateTimeString());
        $this->assertCount(1, Riddle::where('question', $oldQuestion)->get());
    }

    public function test_proverb_seed_does_not_undo_admin_edits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $proverb = Proverb::first();
        $oldQuestion = $proverb->question;
        $oldAnswer = $proverb->answer;
        $oldCreatedAt = $proverb->created_at;

        $proverb->forceFill([
            'is_suspended' => true,
            'suspended_reason' => 'Admin probe',
            'updated_at' => now()->addDays(10),
        ])->save();

        $this->seed(DatabaseSeeder::class);

        $fresh = Proverb::where('question', $oldQuestion)
            ->where('answer', $oldAnswer)
            ->first();

        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_suspended);
        $this->assertSame('Admin probe', $fresh->suspended_reason);
        $this->assertSame($oldCreatedAt->toDateTimeString(), $fresh->created_at->toDateTimeString());
        $this->assertCount(1, Proverb::where('question', $oldQuestion)->where('answer', $oldAnswer)->get());
    }

    public function test_joke_seed_does_not_undo_admin_edits(): void
    {
        $this->seed(DatabaseSeeder::class);

        $joke = Joke::first();
        $oldSetup = $joke->setup;
        $oldPunchline = $joke->punchline;
        $oldCreatedAt = $joke->created_at;

        $joke->forceFill([
            'distractors' => ['custom', 'option', 'choices'],
            'is_suspended' => true,
            'suspended_reason' => 'Admin probe',
            'updated_at' => now()->addDays(10),
        ])->save();

        $this->seed(DatabaseSeeder::class);

        $fresh = Joke::where('setup', $oldSetup)
            ->where('punchline', $oldPunchline)
            ->first();

        $this->assertNotNull($fresh);
        $this->assertSame(['custom', 'option', 'choices'], $fresh->distractors);
        $this->assertTrue($fresh->is_suspended);
        $this->assertSame('Admin probe', $fresh->suspended_reason);
        $this->assertSame($oldCreatedAt->toDateTimeString(), $fresh->created_at->toDateTimeString());
        $this->assertCount(1, Joke::where('setup', $oldSetup)->where('punchline', $oldPunchline)->get());
    }
}