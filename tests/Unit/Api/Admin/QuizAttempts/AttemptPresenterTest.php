<?php
/**
 * Tests for the admin view of quiz answers.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin\QuizAttempts;

use LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptPresenter;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptPresenter
 */
final class AttemptPresenterTest extends MonkeyTestCase {

	/**
	 * A stored snapshot: one wrong single choice, one wrong true/false.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function snapshot(): array {
		return [
			[
				'id'           => 'q1',
				'type'         => 'single',
				'prompt'       => 'Capital?',
				'given'        => 'b',
				'given_text'   => 'Vienna',
				'correct'      => false,
				'correct_text' => 'Budapest',
			],
			[
				'id'             => 'q2',
				'type'           => 'boolean',
				'prompt'         => 'Water is wet?',
				'given'          => false,
				'correct'        => false,
				'correct_answer' => true,
			],
		];
	}

	public function test_non_administrators_never_see_the_answer_key(): void {
		$answers = AttemptPresenter::answers( self::snapshot(), false );

		foreach ( $answers as $answer ) {
			$this->assertArrayNotHasKey( 'correct', $answer );
			$this->assertArrayNotHasKey( 'correctText', $answer );
			$this->assertArrayNotHasKey( 'correctAnswer', $answer );
		}
		$this->assertSame( 'Vienna', $answers[0]['givenText'] );
		$this->assertFalse( $answers[1]['given'] );
	}

	public function test_administrators_see_right_and_wrong_with_the_right_answer(): void {
		$answers = AttemptPresenter::answers( self::snapshot(), true );

		$this->assertFalse( $answers[0]['correct'] );
		$this->assertSame( 'Budapest', $answers[0]['correctText'] );
		$this->assertTrue( $answers[1]['correctAnswer'] );
	}

	public function test_skips_malformed_entries(): void {
		$this->assertSame( [], AttemptPresenter::answers( [ 'x', 3, null ], true ) );
	}
}
