<?php
/**
 * Tests for the quiz attempt search query builder.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Quiz;

use LightweightPlugins\LMS\Quiz\QuizAttemptSearch;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Quiz\QuizAttemptSearch
 */
final class QuizAttemptSearchTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = (object) [ 'prefix' => 'wp_' ];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_builds_every_filter_with_placeholders(): void {
		[ $from, $where, $args ] = QuizAttemptSearch::where(
			[
				'course' => 3,
				'lesson' => 7,
				'user'   => 11,
				'passed' => false,
				'from'   => '2026-09-01',
				'to'     => '2026-09-30',
			],
			'wp_users',
			''
		);

		$this->assertSame( 'wp_lms_quiz_attempts q', $from );
		$this->assertSame(
			'1=1 AND q.course_id = %d AND q.lesson_id = %d AND q.user_id = %d AND q.passed = %d AND q.submitted_at >= %s AND q.submitted_at <= %s',
			$where
		);
		$this->assertSame( [ 3, 7, 11, 0, '2026-09-01 00:00:00', '2026-09-30 23:59:59' ], $args );
	}

	public function test_search_joins_users(): void {
		[ $from, $where, $args ] = QuizAttemptSearch::where( [], 'wp_users', 'kiss' );

		$this->assertStringContainsString( 'INNER JOIN wp_users u ON u.ID = q.user_id', $from );
		$this->assertStringContainsString( 'u.user_email LIKE %s', $where );
		$this->assertSame( [ '%kiss%', '%kiss%', '%kiss%' ], $args );
	}
}
