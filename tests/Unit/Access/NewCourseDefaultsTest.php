<?php
/**
 * Tests for new course defaults.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\NewCourseDefaults;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the default_access_type setting was never read, so new courses
 * were always free.
 *
 * @covers \LightweightPlugins\LMS\Access\NewCourseDefaults
 */
final class NewCourseDefaultsTest extends MonkeyTestCase {

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

	public function test_new_course_gets_the_configured_access_type(): void {
		Functions\when( 'get_option' )->justReturn( [ 'default_access_type' => 'paid' ] );
		Functions\when( 'metadata_exists' )->justReturn( false );
		Functions\expect( 'update_post_meta' )->once()->with( 5, Options::META_PREFIX . 'access_type', 'paid' )->andReturn( true );

		NewCourseDefaults::apply( 5, new \WP_Post( [ 'ID' => 5 ] ), false );
	}

	public function test_updates_and_courses_with_a_type_are_left_alone(): void {
		Functions\when( 'get_option' )->justReturn( [ 'default_access_type' => 'paid' ] );
		Functions\when( 'metadata_exists' )->justReturn( true );
		Functions\expect( 'update_post_meta' )->never();

		NewCourseDefaults::apply( 5, new \WP_Post( [ 'ID' => 5 ] ), true );
		NewCourseDefaults::apply( 5, new \WP_Post( [ 'ID' => 5 ] ), false );
	}

	public function test_invalid_setting_falls_back_to_free(): void {
		Functions\when( 'get_option' )->justReturn( [ 'default_access_type' => 'vip' ] );

		$this->assertSame( 'free', NewCourseDefaults::access_type() );
	}
}
