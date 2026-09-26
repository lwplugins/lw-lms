<?php
/**
 * Tests for the preview lesson list.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\PreviewLessons;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the course builder read a per-lesson `preview_lesson` meta that
 * nothing writes, so saved preview lessons never showed as checked.
 *
 * @covers \LightweightPlugins\LMS\Access\PreviewLessons
 */
final class PreviewLessonsTest extends MonkeyTestCase {

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

	public function test_reads_the_course_level_list_as_ints(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, Options::META_PREFIX . 'preview_lesson_ids', true )
			->andReturn( [ '10', 11, 0, 'x' ] );

		$this->assertSame( [ 10, 11 ], PreviewLessons::stored( 42 ) );
	}

	public function test_missing_list_is_empty(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$this->assertSame( [], PreviewLessons::stored( 42 ) );
	}

	/**
	 * Regression: the enable_preview_lessons setting was never read.
	 */
	public function test_turning_the_setting_off_disables_every_preview(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enable_preview_lessons' => false ] );
		Functions\when( 'get_post_meta' )->justReturn( [ 10 ] );

		$this->assertSame( [], PreviewLessons::active( 42 ) );
		$this->assertSame( [ 10 ], PreviewLessons::stored( 42 ), 'the stored list is kept' );
	}

	public function test_previews_count_by_default(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_post_meta' )->justReturn( [ 10 ] );

		$this->assertSame( [ 10 ], PreviewLessons::active( 42 ) );
	}
}
