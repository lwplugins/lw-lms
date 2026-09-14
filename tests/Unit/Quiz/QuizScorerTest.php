<?php
/**
 * Tests for server-side quiz scoring.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizScorer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizScorer
 */
final class QuizScorerTest extends TestCase {

	public function test_scores_mixed_quiz_and_excludes_open_questions(): void {
		$result = QuizScorer::score(
			self::quiz(),
			[
				'q_single_a' => 0,
				'q_single_b' => 0,
				'q_bool_a'   => true,
				'q_bool_b'   => true,
				'q_open'     => 'Szabad szöveg',
			],
			80.0
		);

		$this->assertSame( 2, $result['score'] );
		$this->assertSame( 4, $result['scored_questions'] );
		$this->assertSame( 50.0, $result['percentage'] );
		$this->assertFalse( $result['passed'] );
	}

	public function test_reveals_correct_answer_only_for_wrong_answers(): void {
		$result = QuizScorer::score(
			self::quiz(),
			[
				'q_single_a' => 0,
				'q_single_b' => 0,
				'q_bool_a'   => true,
				'q_bool_b'   => true,
			],
			80.0
		);

		$this->assertSame(
			[
				'q_single_a' => [ 'correct' => true ],
				'q_single_b' => [
					'correct'        => false,
					'correct_option' => 2,
				],
				'q_bool_a'   => [ 'correct' => true ],
				'q_bool_b'   => [
					'correct'        => false,
					'correct_answer' => false,
				],
				'q_open'     => [
					'scored' => false,
					'sample' => 'Minta.',
				],
			],
			$result['results']
		);
	}

	public function test_missing_answers_count_as_wrong(): void {
		$result = QuizScorer::score( self::quiz(), [], 50.0 );

		$this->assertSame( 0, $result['score'] );
		$this->assertSame( 4, $result['scored_questions'] );
		$this->assertSame( 0.0, $result['percentage'] );
	}

	/**
	 * @dataProvider provide_answer_types
	 */
	public function test_answer_type_handling( mixed $single_answer, mixed $bool_answer, int $expected_score ): void {
		$result = QuizScorer::score(
			self::quiz(),
			[
				'q_single_b' => $single_answer,
				'q_bool_b'   => $bool_answer,
			],
			50.0
		);

		$this->assertSame( $expected_score, $result['score'] );
	}

	public static function provide_answer_types(): array {
		return [
			'int index and bool'        => [ 2, false, 2 ],
			'digit-string index'        => [ '2', false, 2 ],
			'non-digit string index'    => [ 'two', false, 1 ],
			'boolean given as string'   => [ 2, 'false', 1 ],
			'boolean given as int zero' => [ 2, 0, 1 ],
		];
	}

	/**
	 * @dataProvider provide_thresholds
	 */
	public function test_passed_compares_against_threshold( float $threshold, bool $expected ): void {
		$answers = [
			'q_single_a' => 0,
			'q_single_b' => 2,
			'q_bool_a'   => true,
		];

		$this->assertSame( $expected, QuizScorer::score( self::quiz(), $answers, $threshold )['passed'] );
	}

	public static function provide_thresholds(): array {
		return [
			'below 75%'    => [ 70.0, true ],
			'exactly 75%'  => [ 75.0, true ],
			'above 75%'    => [ 75.01, false ],
		];
	}

	public function test_percentage_is_rounded_to_two_decimals(): void {
		$quiz = [ 'questions' => array_slice( self::quiz()['questions'], 0, 3 ) ];

		$result = QuizScorer::score( $quiz, [ 'q_single_a' => 0 ], 33.33 );

		$this->assertSame( 33.33, $result['percentage'] );
		$this->assertTrue( $result['passed'] );
	}

	public function test_open_only_quiz_counts_as_passed(): void {
		$quiz = [ 'questions' => [ self::quiz()['questions'][4] ] ];

		$result = QuizScorer::score( $quiz, [], 80.0 );

		$this->assertSame( 0, $result['scored_questions'] );
		$this->assertSame( 100.0, $result['percentage'] );
		$this->assertTrue( $result['passed'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function quiz(): array {
		return [
			'questions' => [
				[
					'id'      => 'q_single_a',
					'type'    => 'single',
					'prompt'  => 'A?',
					'options' => [
						[
							'text'    => 'Yes',
							'correct' => true,
						],
						[ 'text' => 'No' ],
					],
				],
				[
					'id'      => 'q_single_b',
					'type'    => 'single',
					'prompt'  => 'B?',
					'options' => [
						[ 'text' => 'One' ],
						[
							'text'    => 'Two',
							'correct' => false,
						],
						[
							'text'    => 'Three',
							'correct' => true,
						],
					],
				],
				[
					'id'      => 'q_bool_a',
					'type'    => 'boolean',
					'prompt'  => 'True?',
					'correct' => true,
				],
				[
					'id'      => 'q_bool_b',
					'type'    => 'boolean',
					'prompt'  => 'False?',
					'correct' => false,
				],
				[
					'id'     => 'q_open',
					'type'   => 'open',
					'prompt' => 'Why?',
					'sample' => 'Minta.',
				],
			],
		];
	}
}
