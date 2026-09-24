<?php

namespace Tests\Unit\Support;

use App\Support\AnswerMatcher;
use PHPUnit\Framework\TestCase;

class AnswerMatcherTest extends TestCase
{
    public function test_exact_match_is_correct(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('inkerebuzo', 'inkerebuzo'));
    }

    public function test_case_and_diacritics_are_ignored(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('INKEREBUZO', 'Inkerebuzó'));
    }

    public function test_surrounding_whitespace_is_ignored(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('   inkerebuzo   ', 'inkerebuzo'));
    }

    public function test_free_word_order_is_accepted(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('umuhinzi n’ingingo', 'ingingo n’umuhinzi'));
    }

    public function test_slash_separated_alternative_is_accepted(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('akayuki', 'uruyuki/akayuki'));
    }

    public function test_partial_single_content_word_is_accepted(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('umuriro', 'akamuriro k’umuriro'));
    }

    public function test_typo_is_tolerated(): void
    {
        $this->assertTrue(AnswerMatcher::isCorrect('inkerebuza', 'inkerebuzo'));
    }

    public function test_wrong_answer_is_rejected(): void
    {
        $this->assertFalse(AnswerMatcher::isCorrect('amagara', 'inkerebuzo'));
    }

    public function test_empty_guess_is_rejected(): void
    {
        $this->assertFalse(AnswerMatcher::isCorrect('', 'inkerebuzo'));
    }

    public function test_allow_partial_can_be_disabled(): void
    {
        $this->assertFalse(AnswerMatcher::isCorrect('uruyuki', 'akamuriro k’umuriro', [
            'allowPartial' => false,
        ]));
    }

    public function test_concede_gesture_is_detected(): void
    {
        $this->assertTrue(AnswerMatcher::isConcede('  Ndaguhaye  '));
        $this->assertFalse(AnswerMatcher::isConcede('inkerebuzo'));
    }
}
