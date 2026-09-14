<?php
/**
 * Tests for quiz submission side effects (attempt record + hooks).
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Quiz\QuizSubmission;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizSubmission
 * @covers \LightweightPlugins\LMS\Quiz\QuizAttempts
 */
final class QuizSubmissionTest extends MonkeyTestCase {

	private const QUIZ = [
		'pass_percentage' => 50,
		'questions'       => [
			[
				'id'      => 'q1',
				'type'    => 'boolean',
				'prompt'  => 'True?',
				'correct' => true,
			],
		],
	];

	/**
	 * Rows the $wpdb double received.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $inserted = [];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		// require_quiz_pass stays off: no progress writes in these tests.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
		Functions\when( 'current_time' )->justReturn( '2026-09-14 10:00:00' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'get_post_meta' )->justReturn( 42 );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $data ): string => (string) json_encode( $data ) );

		$this->inserted  = [];
		$rows            = &$this->inserted;
		$GLOBALS['wpdb'] = new class( $rows ) {
			public string $prefix = 'wp_';
			public int $insert_id = 7;
			/** @var array<int, array<string, mixed>> */
			private array $rows;
			public function __construct( array &$rows ) {
				$this->rows = &$rows;
			}
			public function insert( string $table, array $data ): int {
				$this->rows[] = [ 'table' => $table ] + $data;
				return 1;
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_passing_fires_submitted_and_passed_with_documented_args(): void {
		Functions\expect( 'update_user_meta' )->once();
		Actions\expectDone( 'lw_lms_quiz_submitted' )->once()->with( 99, 7, 100.0, true );
		Actions\expectDone( 'lw_lms_quiz_passed' )->once()->with( 99, 7, 100.0 );

		$result = QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => true ] );

		$this->assertTrue( $result['passed'] );
	}

	public function test_failing_fires_only_submitted(): void {
		Functions\expect( 'update_user_meta' )->once();
		Actions\expectDone( 'lw_lms_quiz_submitted' )->once()->with( 99, 7, 0.0, false );
		Actions\expectDone( 'lw_lms_quiz_passed' )->never();

		$result = QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => false ] );

		$this->assertFalse( $result['passed'] );
	}

	public function test_summary_records_pass_timestamp_and_running_totals(): void {
		Functions\expect( 'update_user_meta' )->once()->with(
			7,
			'_lw_lms_quiz_99',
			[
				'percentage'      => 100.0,
				'passed'          => true,
				'submitted_at'    => '2026-09-14 10:00:00',
				'passed_at'       => '2026-09-14 10:00:00',
				'attempts'        => 1,
				'best_percentage' => 100.0,
			]
		);

		$result = QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => true ] );

		$this->assertSame( 100.0, $result['percentage'] );
	}

	public function test_a_failed_retry_keeps_the_earlier_pass_and_best_score(): void {
		Functions\when( 'get_user_meta' )->justReturn(
			[
				'percentage'      => 100.0,
				'passed'          => true,
				'submitted_at'    => '2026-09-13 08:00:00',
				'passed_at'       => '2026-09-13 08:00:00',
				'attempts'        => 2,
				'best_percentage' => 100.0,
			]
		);
		Functions\expect( 'update_user_meta' )->once()->with(
			7,
			'_lw_lms_quiz_99',
			[
				'percentage'      => 0.0,
				'passed'          => false,
				'submitted_at'    => '2026-09-14 10:00:00',
				'passed_at'       => '2026-09-13 08:00:00',
				'attempts'        => 3,
				'best_percentage' => 100.0,
			]
		);

		$result = QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => false ] );

		$this->assertFalse( $result['passed'] );
	}

	public function test_stores_one_attempt_row_with_the_answer_snapshot(): void {
		Functions\when( 'update_user_meta' )->justReturn( true );

		QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => false ] );

		$this->assertCount( 1, $this->inserted );
		$row = $this->inserted[0];

		$this->assertSame( 'wp_lms_quiz_attempts', $row['table'] );
		$this->assertSame( 7, $row['user_id'] );
		$this->assertSame( 99, $row['lesson_id'] );
		$this->assertSame( 42, $row['course_id'] );
		$this->assertSame( 0.0, $row['percentage'] );
		$this->assertSame( 0, $row['passed'] );
		$this->assertSame( '2026-09-14 10:00:00', $row['submitted_at'] );
		$this->assertSame(
			[
				[
					'id'             => 'q1',
					'type'           => 'boolean',
					'prompt'         => 'True?',
					'given'          => false,
					'correct'        => false,
					'correct_answer' => true,
				],
			],
			json_decode( (string) $row['answers'], true )
		);
	}
}
