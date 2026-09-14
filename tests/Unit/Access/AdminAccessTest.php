<?php
/**
 * Tests for the staff (admin) access bypass.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AdminAccess;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Access\AdminAccess
 */
final class AdminAccessTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		parent::tearDown();
	}

	public function test_does_not_consult_capabilities_when_setting_is_off(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'user_can' )->never();

		$this->assertFalse( AdminAccess::applies( 7 ) );
	}

	public function test_never_applies_to_guests(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\expect( 'user_can' )->never();

		$this->assertFalse( AdminAccess::applies( 0 ) );
	}

	public function test_applies_to_user_with_manage_lms_by_default(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\expect( 'user_can' )->once()->with( 7, 'manage_lms' )->andReturn( true );

		$this->assertTrue( AdminAccess::applies( 7 ) );
	}

	public function test_does_not_apply_to_user_without_capability(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\when( 'user_can' )->justReturn( false );

		$this->assertFalse( AdminAccess::applies( 7 ) );
	}

	public function test_capability_is_overridable_via_filter(): void {
		Functions\when( 'get_option' )->justReturn( [ 'auto_enroll_admins' => true ] );
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $tag, mixed $value ): mixed =>
				'lw_lms_admin_access_capability' === $tag ? 'edit_courses' : $value
		);
		Functions\expect( 'user_can' )->once()->with( 7, 'edit_courses' )->andReturn( true );

		$this->assertTrue( AdminAccess::applies( 7 ) );
	}
}
