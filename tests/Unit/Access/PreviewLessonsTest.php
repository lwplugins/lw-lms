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
}
