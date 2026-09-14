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
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Access\AccessChecker
 */
final class AccessCheckerTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Options::clear_cache();
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
		$GLOBALS['wpdb'] = $this->empty_access_table();

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

		$GLOBALS['wpdb'] = $this->empty_access_table();

		$this->assertFalse( AccessChecker::has_course_access( 456, 7 ) );
	}

	/**
	 * No $wpdb global is set: reaching the access table (or the free-course
	 * lazy grant) would fatal, so a pass proves the bypass touches no DB.
	 */
	public function test_admin_bypass_grants_paid_course_without_touching_access_table(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\when( 'get_post_meta' )->justReturn( 'paid' );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'user_can' )->justReturn( true );

		$this->assertTrue( AccessChecker::has_course_access( 456, 7 ) );
	}

	public function test_admin_bypass_skips_free_course_lazy_grant(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\when( 'get_post_meta' )->justReturn( 'free' );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'user_can' )->justReturn( true );
		Functions\expect( 'do_action' )->never();

		$this->assertTrue( AccessChecker::has_course_access( 456, 7 ) );
	}

	public function test_paid_course_ignores_capabilities_when_admin_access_is_off(): void {
		Functions\when( 'get_post_meta' )->justReturn( 'paid' );
		Functions\when( 'current_time' )->justReturn( '2026-07-18 00:00:00' );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\expect( 'user_can' )->never();

		$GLOBALS['wpdb'] = $this->empty_access_table();

		$this->assertFalse( AccessChecker::has_course_access( 456, 7 ) );
	}

	/**
	 * A $wpdb double whose access table holds no rows.
	 *
	 * @return object
	 */
	private function empty_access_table(): object {
		return new class() {
			public string $prefix = 'wp_';
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_var( string $query ): mixed {
				return null;
			}
		};
	}
}
