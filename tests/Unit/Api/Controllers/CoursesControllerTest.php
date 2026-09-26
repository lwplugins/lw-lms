<?php
/**
 * Tests for the course collection page size.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Controllers;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Controllers\CoursesController;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the route's per_page default (10) always won, so the
 * courses_per_page setting was never used.
 *
 * @covers \LightweightPlugins\LMS\Api\Controllers\CoursesController
 */
final class CoursesControllerTest extends MonkeyTestCase {

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
	 * @dataProvider provide_page_sizes
	 */
	public function test_page_size( mixed $requested, int $setting, int $expected ): void {
		Functions\when( 'get_option' )->justReturn( [ 'courses_per_page' => $setting ] );

		$this->assertSame( $expected, CoursesController::per_page( $requested ) );
	}

	public static function provide_page_sizes(): array {
		return [
			'setting when not requested'   => [ null, 24, 24 ],
			'request wins over setting'    => [ '5', 24, 5 ],
			'setting 0 is clamped to 1'    => [ null, 0, 1 ],
			'setting 1000 clamped to 100'  => [ null, 1000, 100 ],
		];
	}
}
