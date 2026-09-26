<?php
/**
 * Tests for manual enrollment expiry.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin\UserProfile;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AccessQueries;
use LightweightPlugins\LMS\Admin\UserProfile\EnrollmentHandler;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: order grants stored expires_at in UTC, the profile stored
 * site-local 23:59:59, and the check compared with site-local now, so
 * time-limited access ended early or late by the UTC offset.
 *
 * @covers \LightweightPlugins\LMS\Admin\UserProfile\EnrollmentHandler
 * @covers \LightweightPlugins\LMS\Access\AccessQueries
 */
final class EnrollmentHandlerTest extends MonkeyTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_profile_expiry_is_the_end_of_the_local_day_in_utc(): void {
		// Site in UTC+2.
		Functions\expect( 'get_gmt_from_date' )->once()->with( '2026-10-01 23:59:59' )->andReturn( '2026-10-01 21:59:59' );

		$this->assertSame( '2026-10-01 21:59:59', EnrollmentHandler::expiry_from_date( '2026-10-01' ) );
	}

	public function test_invalid_date_means_no_expiry(): void {
		$this->assertNull( EnrollmentHandler::expiry_from_date( '01/10/2026' ) );
	}

	public function test_access_check_compares_expiry_with_utc_now(): void {
		Functions\expect( 'current_time' )->once()->with( 'mysql', true )->andReturn( '2026-10-01 22:00:00' );
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			/** @var array<int, mixed> */
			public array $args = [];
			public function prepare( string $query, mixed ...$args ): string {
				$this->args = $args;
				return $query;
			}
			public function get_var( string $query ): mixed {
				return null;
			}
		};

		AccessQueries::has_active_access( 7, 42 );

		$this->assertSame( '2026-10-01 22:00:00', end( $GLOBALS['wpdb']->args ) );
	}
}
