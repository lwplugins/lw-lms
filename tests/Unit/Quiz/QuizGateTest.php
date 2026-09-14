<?php
/**
 * Tests for the require_quiz_pass completion gate.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Quiz\QuizGate;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizGate
 */
final class QuizGateTest extends MonkeyTestCase {

	private const QUIZ = [
		'questions' => [
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
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_never_blocks_when_setting_is_off(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'get_post_meta' )->never();
		Functions\expect( 'get_user_meta' )->never();

		$this->assertFalse( QuizGate::blocks_completion( 7, 99 ) );
	}

	public function test_does_not_block_lessons_without_quiz(): void {
		Functions\when( 'get_option' )->justReturn( [ 'require_quiz_pass' => true ] );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->assertFalse( QuizGate::blocks_completion( 7, 99 ) );
	}

	public function test_blocks_until_the_learner_passes(): void {
		Functions\when( 'get_option' )->justReturn( [ 'require_quiz_pass' => true ] );
		Functions\when( 'get_post_meta' )->justReturn( self::QUIZ );
		Functions\when( 'get_user_meta' )->justReturn(
			[
				'percentage'   => 0.0,
				'passed'       => false,
				'submitted_at' => '2026-09-11 10:00:00',
				'passed_at'    => null,
			]
		);

		$this->assertTrue( QuizGate::blocks_completion( 7, 99 ) );
	}

	public function test_a_later_failed_retry_does_not_relock_a_passed_lesson(): void {
		Functions\when( 'get_option' )->justReturn( [ 'require_quiz_pass' => true ] );
		Functions\when( 'get_post_meta' )->justReturn( self::QUIZ );
		Functions\when( 'get_user_meta' )->justReturn(
			[
				'percentage'   => 0.0,
				'passed'       => false,
				'submitted_at' => '2026-09-11 11:00:00',
				'passed_at'    => '2026-09-11 10:00:00',
			]
		);

		$this->assertFalse( QuizGate::blocks_completion( 7, 99 ) );
	}
}
