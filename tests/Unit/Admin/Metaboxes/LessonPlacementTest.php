<?php
/**
 * Tests for lesson course/section placement.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin\Metaboxes;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonPlacement;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the lesson metabox saved whatever section was selected, even
 * one of the previously chosen course, and lowercased its ID.
 *
 * @covers \LightweightPlugins\LMS\Admin\Metaboxes\LessonPlacement
 */
final class LessonPlacementTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_post' )->alias(
			static fn ( int $id ): ?\WP_Post => [ 42 => 'course', 43 => 'course', 7 => 'page' ][ $id ] ?? null
				? new \WP_Post( [ 'ID' => $id, 'post_type' => [ 42 => 'course', 43 => 'course', 7 => 'page' ][ $id ] ] )
				: null
		);
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id ): mixed => [
				42 => [ [ 'id' => 'Sec_A', 'title' => 'Intro' ] ],
				43 => [ [ 'id' => 'sec_b', 'title' => 'Other' ] ],
			][ $id ] ?? ''
		);
	}

	public function test_section_of_the_chosen_course_is_kept_with_its_case(): void {
		$this->assertSame( 'Sec_A', LessonPlacement::section( 'Sec_A', 42 ) );
	}

	public function test_section_of_another_course_is_dropped(): void {
		$this->assertSame( '', LessonPlacement::section( 'sec_b', 42 ) );
	}

	public function test_section_without_course_is_dropped(): void {
		$this->assertSame( '', LessonPlacement::section( 'Sec_A', 0 ) );
	}

	public function test_only_courses_are_accepted_as_course(): void {
		$this->assertSame( 42, LessonPlacement::course( 42 ) );
		$this->assertSame( 0, LessonPlacement::course( 7 ) );
		$this->assertSame( 0, LessonPlacement::course( 999 ) );
	}
}
