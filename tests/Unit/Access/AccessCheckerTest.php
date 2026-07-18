<?php
/**
 * Tests for course access resolution.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Access\AccessChecker
 */
final class AccessCheckerTest extends MonkeyTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_open_course_is_accessible_without_login(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'get_post_meta' )->justReturn( 'open' );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertTrue( AccessChecker::has_course_access( 123 ) );
	}

	/**
	 * Regression for issue #14: the lw_lms_has_course_access filter used to be
	 * unreachable for PAID courses because the paid branch returned
	 * has_legacy_purchase() first. An extension that grants access via the
	 * filter must now be honoured after the built-in checks all decline.
	 */
	public function test_paid_course_filter_can_grant_access_after_builtin_checks_decline(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_post_meta' )->justReturn( 'paid' );
		Functions\when( 'current_time' )->justReturn( '2026-07-18 00:00:00' );

		// The access table reports no active grant; every WooCommerce checker is
		// guarded by function_exists()/is_active() and returns false with WC absent.
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_var( string $query ): mixed {
				return null;
			}
		};

		// The extension grants access via the filter (which must now be reached).
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $tag, mixed $value, mixed ...$args ) =>
				'lw_lms_has_course_access' === $tag ? true : $value
		);

		$this->assertTrue( AccessChecker::has_course_access( 456, 7 ) );
	}

	public function test_paid_course_denied_when_no_check_and_no_filter_grant(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'get_post_meta' )->justReturn( 'paid' );
		Functions\when( 'current_time' )->justReturn( '2026-07-18 00:00:00' );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_var( string $query ): mixed {
				return null;
			}
		};

		$this->assertFalse( AccessChecker::has_course_access( 456, 7 ) );
	}
}
