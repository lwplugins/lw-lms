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

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		// require_quiz_pass stays off: no progress writes in these tests.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
		Functions\when( 'current_time' )->justReturn( '2026-09-11 10:00:00' );
		Functions\when( 'get_user_meta' )->justReturn( '' );
	}

	protected function tearDown(): void {
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

	public function test_records_last_attempt_with_pass_timestamp(): void {
		Functions\expect( 'update_user_meta' )->once()->with(
			7,
			'_lw_lms_quiz_99',
			[
				'percentage'   => 100.0,
				'passed'       => true,
				'submitted_at' => '2026-09-11 10:00:00',
				'passed_at'    => '2026-09-11 10:00:00',
			]
		);

		$result = QuizSubmission::submit( 99, 7, self::QUIZ, [ 'q1' => true ] );

		$this->assertSame( 50.0, $result['pass_percentage'] );
	}
}
