<?php
/**
 * Tests for the self-contained answer snapshot.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizScorer;
use LightweightPlugins\LMS\Quiz\QuizSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * The snapshot must stay readable after the quiz itself is edited, so it
 * carries prompts and option texts, not just ids and indexes.
 *
 * @covers \LightweightPlugins\LMS\Quiz\QuizSnapshot
 */
final class QuizSnapshotTest extends TestCase {

	public function test_records_prompt_and_texts_for_a_wrong_single_answer(): void {
		$snapshot = self::snapshot( [ 'q_single' => 0 ] );

		$this->assertSame(
			[
				'id'           => 'q_single',
				'type'         => 'single',
				'prompt'       => 'Pick one',
				'given'        => 'o0',
				'given_text'   => 'A',
				'correct'      => false,
				'correct_text' => 'B',
			],
			$snapshot[0]
		);
	}

	public function test_records_a_right_single_answer_without_revealing_more(): void {
		$snapshot = self::snapshot( [ 'q_single' => 1 ] );

		$this->assertSame(
			[
				'id'         => 'q_single',
				'type'       => 'single',
				'prompt'     => 'Pick one',
				'given'      => 'o1',
				'given_text' => 'B',
				'correct'    => true,
			],
			$snapshot[0]
		);
	}

	public function test_records_boolean_answers_with_the_expected_value(): void {
		$snapshot = self::snapshot( [ 'q_bool' => false ] );

		$this->assertSame(
			[
				'id'             => 'q_bool',
				'type'           => 'boolean',
				'prompt'         => 'True?',
				'given'          => false,
				'correct'        => false,
				'correct_answer' => true,
			],
			$snapshot[1]
		);
	}

	public function test_records_open_answers_as_unscored_text(): void {
		$snapshot = self::snapshot( [ 'q_open' => '  free text  ' ] );

		$this->assertSame(
			[
				'id'         => 'q_open',
				'type'       => 'open',
				'prompt'     => 'Explain',
				'given_text' => 'free text',
				'scored'     => false,
			],
			$snapshot[2]
		);
	}

	public function test_missing_answers_are_recorded_as_not_given(): void {
		$snapshot = self::snapshot( [] );

		$this->assertNull( $snapshot[0]['given'] );
		$this->assertArrayNotHasKey( 'given_text', $snapshot[0] );
		$this->assertFalse( $snapshot[0]['correct'] );
		$this->assertSame( 'B', $snapshot[0]['correct_text'] );
		$this->assertNull( $snapshot[1]['given'] );
		$this->assertArrayNotHasKey( 'given_text', $snapshot[2] );
	}

	public function test_keeps_one_entry_per_question_in_quiz_order(): void {
		$snapshot = self::snapshot( [ 'q_single' => 1 ] );

		$this->assertSame( [ 'q_single', 'q_bool', 'q_open' ], array_column( $snapshot, 'id' ) );
	}

	public function test_survives_json_round_trip(): void {
		$snapshot = self::snapshot( [ 'q_single' => 0, 'q_open' => 'ékezetes "idézet"' ] );

		$this->assertSame( $snapshot, json_decode( (string) json_encode( $snapshot ), true ) );
	}

	/**
	 * @param array<string, mixed> $answers Answers.
	 * @return array<int, array<string, mixed>>
	 */
	private static function snapshot( array $answers ): array {
		$quiz = self::quiz();

		return QuizSnapshot::build( $quiz, $answers, QuizScorer::score( $quiz, $answers, 50.0 )['results'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function quiz(): array {
		return [
			'questions' => [
				[
					'id'      => 'q_single',
					'type'    => 'single',
					'prompt'  => 'Pick one',
					'options' => [
						[ 'text' => 'A' ],
						[
							'text'    => 'B',
							'correct' => true,
						],
					],
				],
				[
					'id'      => 'q_bool',
					'type'    => 'boolean',
					'prompt'  => 'True?',
					'correct' => true,
				],
				[
					'id'     => 'q_open',
					'type'   => 'open',
					'prompt' => 'Explain',
					'sample' => 'Sample',
				],
			],
		];
	}
}
