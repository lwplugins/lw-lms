<?php
/**
 * Tests for quiz document validation / normalization.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\InvalidQuizException;
use LightweightPlugins\LMS\Quiz\QuizNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizNormalizer
 */
final class QuizNormalizerTest extends TestCase {

	private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	public function test_valid_quiz_round_trips_byte_identical(): void {
		$json = (string) json_encode( self::valid_quiz(), self::JSON_FLAGS );

		$normalized = QuizNormalizer::normalize( json_decode( $json, true ) );

		$this->assertSame( $json, json_encode( $normalized, self::JSON_FLAGS ) );
	}

	public function test_optional_root_keys_stay_absent_when_not_given(): void {
		$quiz = self::valid_quiz();
		unset( $quiz['pass_percentage'], $quiz['shuffle_options'] );

		$this->assertSame( $quiz, QuizNormalizer::normalize( $quiz ) );
	}

	public function test_canonical_key_order_is_applied(): void {
		$quiz = [
			'questions' => [
				[
					'correct' => false,
					'prompt'  => 'Reversed keys?',
					'type'    => 'boolean',
					'id'      => 'q_order',
				],
			],
		];

		$this->assertSame(
			[ 'id', 'type', 'prompt', 'correct' ],
			array_keys( QuizNormalizer::normalize( $quiz )['questions'][0] )
		);
	}

	/**
	 * @dataProvider provide_invalid_quizzes
	 */
	public function test_rejects_invalid_quiz( callable $mutate, string $message_fragment ): void {
		$quiz = $mutate( self::valid_quiz() );

		$this->expectException( InvalidQuizException::class );
		$this->expectExceptionMessage( $message_fragment );

		QuizNormalizer::normalize( $quiz );
	}

	public static function provide_invalid_quizzes(): array {
		return [
			'not an object'            => [ static fn () => [ 1, 2 ], 'quiz must be an object' ],
			'unknown root key'         => [ static fn ( $q ) => $q + [ 'timer' => 60 ], 'quiz has unknown key(s): timer' ],
			'missing questions'        => [ static fn ( $q ) => array_diff_key( $q, [ 'questions' => 1 ] ), 'quiz.questions' ],
			'empty questions'          => [ static fn ( $q ) => array_merge( $q, [ 'questions' => [] ] ), 'quiz.questions' ],
			'pass percentage > 100'    => [ static fn ( $q ) => array_merge( $q, [ 'pass_percentage' => 101 ] ), 'quiz.pass_percentage' ],
			'pass percentage string'   => [ static fn ( $q ) => array_merge( $q, [ 'pass_percentage' => '80' ] ), 'quiz.pass_percentage' ],
			'shuffle not bool'         => [ static fn ( $q ) => array_merge( $q, [ 'shuffle_options' => 1 ] ), 'quiz.shuffle_options' ],
			'unknown type'             => [ static fn ( $q ) => self::with_question( $q, 0, [ 'type' => 'essay' ] ), 'quiz.questions[0].type' ],
			'id with space'            => [ static fn ( $q ) => self::with_question( $q, 0, [ 'id' => 'q 1' ] ), 'quiz.questions[0].id' ],
			'numeric id'               => [ static fn ( $q ) => self::with_question( $q, 0, [ 'id' => '123' ] ), 'quiz.questions[0].id' ],
			'duplicate id'             => [ static fn ( $q ) => self::with_question( $q, 1, [ 'id' => 'q_9e9b2eef93b3' ] ), 'duplicates question q_9e9b2eef93b3' ],
			'empty prompt'             => [ static fn ( $q ) => self::with_question( $q, 0, [ 'prompt' => '  ' ] ), 'quiz.questions[0].prompt' ],
			'unknown question key'     => [ static fn ( $q ) => self::with_question( $q, 0, [ 'points' => 2 ] ), 'quiz.questions[0] has unknown key(s): points' ],
			'single with one option'   => [ static fn ( $q ) => self::with_question( $q, 0, [ 'options' => [ [ 'text' => 'A', 'correct' => true ] ] ] ), 'quiz.questions[0].options' ],
			'single with two correct'  => [ static fn ( $q ) => self::with_question( $q, 0, [ 'options' => [ [ 'text' => 'A', 'correct' => true ], [ 'text' => 'B', 'correct' => true ] ] ] ), 'exactly one option' ],
			'single with none correct' => [ static fn ( $q ) => self::with_question( $q, 0, [ 'options' => [ [ 'text' => 'A' ], [ 'text' => 'B' ] ] ] ), 'exactly one option' ],
			'option correct not bool'  => [ static fn ( $q ) => self::with_question( $q, 0, [ 'options' => [ [ 'text' => 'A', 'correct' => 1 ], [ 'text' => 'B' ] ] ] ), 'quiz.questions[0].options[0].correct' ],
			'boolean without answer'   => [ static fn ( $q ) => self::with_question( $q, 1, [ 'correct' => 'yes' ] ), 'quiz.questions[1].correct' ],
			'open with options'        => [ static fn ( $q ) => self::with_question( $q, 2, [ 'options' => [] ] ), 'quiz.questions[2] has unknown key(s): options' ],
			'open sample not string'   => [ static fn ( $q ) => self::with_question( $q, 2, [ 'sample' => 5 ] ), 'quiz.questions[2].sample' ],
		];
	}

	/**
	 * Quiz in the issue's documented shape (text with unicode, quotes and a backslash).
	 *
	 * @return array<string, mixed>
	 */
	private static function valid_quiz(): array {
		return [
			'pass_percentage' => 80,
			'shuffle_options' => true,
			'questions'       => [
				[
					'id'      => 'q_9e9b2eef93b3',
					'type'    => 'single',
					'prompt'  => 'Melyik az "egészséges" érték? C:\\adat',
					'options' => [
						[
							'text'    => 'Első',
							'correct' => true,
						],
						[ 'text' => 'Második' ],
						[
							'text'    => 'Harmadik',
							'correct' => false,
						],
					],
				],
				[
					'id'      => 'q_e2f6a37486e7',
					'type'    => 'boolean',
					'prompt'  => 'Igaz vagy hamis?',
					'correct' => true,
				],
				[
					'id'     => 'q_3ae2154b9400',
					'type'   => 'open',
					'prompt' => 'Mit tanultál?',
					'sample' => 'Mintaválasz.',
				],
			],
		];
	}

	/**
	 * Merge fields into one question of a quiz.
	 *
	 * @param array<string, mixed> $quiz   Quiz.
	 * @param int                  $index  Question index.
	 * @param array<string, mixed> $fields Fields to merge.
	 * @return array<string, mixed>
	 */
	private static function with_question( array $quiz, int $index, array $fields ): array {
		$quiz['questions'][ $index ] = array_merge( $quiz['questions'][ $index ], $fields );
		return $quiz;
	}
}
