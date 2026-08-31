<?php

namespace Tests\Unit\Support;

use App\Support\RinjoraData;
use App\Support\RinjoraTier;
use PHPUnit\Framework\TestCase;

class RinjoraDataTest extends TestCase
{
    public function test_dataset_counts_match_the_prototype(): void
    {
        $this->assertCount(216, RinjoraData::sokwe());
        $this->assertCount(162, RinjoraData::heraheza());
        $this->assertCount(16, RinjoraData::tujajure());
    }

    public function test_all_rows_are_non_empty_and_utf8(): void
    {
        foreach ([RinjoraData::sokwe(), RinjoraData::heraheza(), RinjoraData::tujajure()] as $set) {
            foreach ($set as $row) {
                $this->assertTrue(mb_check_encoding(implode(' ', $row), 'UTF-8'));
                foreach ($row as $value) {
                    $this->assertNotSame('', $value);
                }
            }
        }
    }

    public function test_sokwe_has_slash_separated_alternatives(): void
    {
        $withAlternatives = array_filter(
            RinjoraData::sokwe(),
            fn ($row) => strpos($row['a'], '/') !== false
        );

        $this->assertGreaterThan(0, count($withAlternatives));
    }

    public function test_all_heraheza_questions_end_with_an_ellipsis(): void
    {
        foreach (RinjoraData::heraheza() as $row) {
            $this->assertStringEndsWith('…', $row['q']);
        }
    }

    public function test_difficulte_weights_answer_twice_the_prompt(): void
    {
        $a = ['q' => 'abcde', 'a' => 'xyz']; // 3*2 + 5 = 11
        $this->assertSame(11, RinjoraTier::difficulte($a));

        $j = ['t' => 'ab', 'p' => 'qrs'];    // 3*2 + 2 = 8
        $this->assertSame(8, RinjoraTier::difficulte($j));
    }

    public function test_pool_for_slices_a_tier_window_sorted_by_difficulty(): void
    {
        // Craft 10 items with increasing difficulty; ask for level 1 of them.
        $source = [];
        for ($i = 1; $i <= 10; $i++) {
            $source[] = ['q' => str_repeat('q', $i), 'a' => str_repeat('a', $i)];
        }

        $result = RinjoraTier::poolFor($source, 1, 10, 5);

        $this->assertCount(10, $result['pool']);
        $this->assertTrue($result['hasNext'] === false || $result['hasNext'] === true);
        // Pools keep sorted order.
        $difficulties = array_map(fn ($it) => RinjoraTier::difficulte($it), $result['pool']);
        $sorted = $difficulties;
        sort($sorted);
        $this->assertSame($sorted, $difficulties);
    }

    public function test_pool_for_respects_round_size_and_lower_levels(): void
    {
        $source = [];
        for ($i = 1; $i <= 100; $i++) {
            $source[] = ['q' => 'q'.$i, 'a' => 'a'.$i];
        }

        $result = RinjoraTier::poolFor($source, 3, 10, 5);

        $this->assertLessThanOrEqual(10, count($result['pool']));
        $this->assertGreaterThan(0, count($result['pool']));
    }

    public function test_qualifies_for_next_respects_min_score(): void
    {
        $this->assertFalse(RinjoraTier::qualifiesForNext(7, 10, 8));
        $this->assertTrue(RinjoraTier::qualifiesForNext(8, 10, 8));
        $this->assertTrue(RinjoraTier::qualifiesForNext(10, 10, 8));
    }
}
