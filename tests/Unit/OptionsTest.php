<?php
/**
 * Tests for the plugin options.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Options;

/**
 * @covers \LightweightPlugins\LMS\Options
 */
final class OptionsTest extends MonkeyTestCase {

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

	/**
	 * show_progress_bar did nothing (the plugin renders no front end) and
	 * was removed; a value still stored from earlier versions is ignored.
	 */
	public function test_removed_setting_is_not_exposed(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'show_progress_bar' => true,
				'courses_per_page'  => 12,
			]
		);

		$all = Options::get_all();

		$this->assertArrayNotHasKey( 'show_progress_bar', $all );
		$this->assertArrayNotHasKey( 'show_progress_bar', Options::get_defaults() );
		$this->assertSame( 12, $all['courses_per_page'] );
	}
}
