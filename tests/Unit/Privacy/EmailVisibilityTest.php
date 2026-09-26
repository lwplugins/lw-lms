<?php
/**
 * Tests for the email visibility rules in the LMS admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Privacy;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\EnrollmentList;
use LightweightPlugins\LMS\Api\Admin\AdminFormat;
use LightweightPlugins\LMS\Privacy\EmailVisibility;
use LightweightPlugins\LMS\Quiz\QuizAttemptSearch;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: every manage_lms user got learners' email addresses in the
 * lists and could search the email column (an email oracle), even without
 * list_users.
 *
 * @covers \LightweightPlugins\LMS\Privacy\EmailVisibility
 * @covers \LightweightPlugins\LMS\Api\Admin\AdminFormat
 * @covers \LightweightPlugins\LMS\Access\EnrollmentList
 * @covers \LightweightPlugins\LMS\Quiz\QuizAttemptSearch
 */
final class EmailVisibilityTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = (object) [ 'prefix' => 'wp_' ];
		Functions\when( 'get_userdata' )->justReturn(
			(object) [
				'display_name' => 'Anna',
				'user_login'   => 'anna',
				'user_email'   => 'anna@example.test',
			]
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * @dataProvider provide_list_users
	 */
	public function test_user_row_carries_email_only_with_list_users( bool $list_users, array $expected ): void {
		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => 'list_users' === $cap && $list_users );

		$this->assertSame( $expected, AdminFormat::user( 7 ) );
	}

	public static function provide_list_users(): array {
		return [
			'LMS manager without list_users' => [
				false,
				[
					'id'    => 7,
					'name'  => 'Anna',
					'login' => 'anna',
				],
			],
			'with list_users'                => [
				true,
				[
					'id'    => 7,
					'name'  => 'Anna',
					'login' => 'anna',
					'email' => 'anna@example.test',
				],
			],
		];
	}

	public function test_search_skips_the_email_column_without_the_flag(): void {
		[ , $enrollments, $args ] = EnrollmentList::where( [], '2026-09-26 10:00:00', 'wp_users', 'anna' );
		[ , $attempts ]           = QuizAttemptSearch::where( [], 'wp_users', 'anna' );

		$this->assertStringNotContainsString( 'user_email', $enrollments );
		$this->assertStringNotContainsString( 'user_email', $attempts );
		$this->assertSame( [ '%anna%', '%anna%' ], $args );
	}

	public function test_search_matches_the_email_column_with_the_flag(): void {
		[ , $where, $args ] = QuizAttemptSearch::where( [ EmailVisibility::SEARCH_FLAG => true ], 'wp_users', 'anna' );

		$this->assertStringContainsString( 'u.user_email LIKE %s', $where );
		$this->assertSame( [ '%anna%', '%anna%', '%anna%' ], $args );
	}
}
