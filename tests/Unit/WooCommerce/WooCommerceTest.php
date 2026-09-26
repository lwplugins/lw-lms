<?php
/**
 * Tests for the WooCommerce integration switch.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\WooCommerce;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Regression: woo_enabled only gated an admin notice that could never show;
 * orders and WooCommerce checks ran regardless.
 *
 * Runs in separate processes: a stand-in WooCommerce class is declared.
 *
 * @covers \LightweightPlugins\LMS\WooCommerce\WooCommerce
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WooCommerceTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			eval( 'final class WooCommerce {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Declares a stand-in class for this process only.
		}
		Options::clear_cache();
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( mixed $args, array $defaults = [] ): array => array_merge( $defaults, (array) $args )
		);
	}

	public function test_integration_follows_the_setting(): void {
		Functions\when( 'get_option' )->justReturn( [ 'woo_enabled' => false ] );
		$this->assertFalse( WooCommerce::is_enabled() );

		Options::clear_cache();
		Functions\when( 'get_option' )->justReturn( [] );
		$this->assertTrue( WooCommerce::is_enabled() );
	}

	/**
	 * @dataProvider provide_switch
	 */
	public function test_subscription_access_follows_the_setting( bool $enabled, bool $expected ): void {
		Functions\when( 'get_option' )->justReturn( [ 'woo_enabled' => $enabled ] );
		Functions\when( 'get_post' )->alias( static fn ( int $id ): \WP_Post => new \WP_Post( [ 'ID' => $id, 'post_type' => 'course' ] ) );
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): mixed => [
				'_lw_lms_access_type'      => 'paid',
				'_lw_lms_subscription_ids' => [ 99 ],
			][ $key ] ?? ''
		);
		Functions\when( 'wcs_user_has_subscription' )->justReturn( true );
		Functions\when( 'current_time' )->justReturn( '2026-09-26 12:00:00' );
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

		$this->assertSame( $expected, AccessChecker::has_course_access( 42, 7 ) );
	}

	public static function provide_switch(): array {
		return [
			'on'  => [ true, true ],
			'off' => [ false, false ],
		];
	}
}
