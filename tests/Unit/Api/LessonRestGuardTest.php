<?php
/**
 * Tests for the core REST lesson route guard.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use LightweightPlugins\LMS\Api\LessonRestGuard;
use PHPUnit\Framework\TestCase;

/**
 * Regression: published lessons (paid content included) were readable by
 * anyone through /wp/v2/lesson, bypassing the /lms/v1 access gate.
 *
 * @covers \LightweightPlugins\LMS\Api\LessonRestGuard
 */
final class LessonRestGuardTest extends TestCase {

	/**
	 * @dataProvider provide_routes
	 */
	public function test_matches_every_core_lesson_route_only( string $route, bool $expected ): void {
		$this->assertSame( $expected, LessonRestGuard::matches( $route, '/wp/v2/lesson' ) );
	}

	public static function provide_routes(): array {
		return [
			'collection'                     => [ '/wp/v2/lesson', true ],
			'single'                         => [ '/wp/v2/lesson/5', true ],
			'revisions'                      => [ '/wp/v2/lesson/5/revisions', true ],
			'autosaves'                      => [ '/wp/v2/lesson/5/autosaves', true ],
			'uppercase (core matches /i)'    => [ '/wp/v2/LESSON/5', true ],
			'mixed case collection'          => [ '/wp/v2/Lesson', true ],
			'trailing slash'                 => [ '/wp/v2/lesson/5/', true ],
			'similar prefix, other route'    => [ '/wp/v2/lessons', false ],
			'similar prefix with dash'       => [ '/wp/v2/lesson-types', false ],
			'course route'                   => [ '/wp/v2/course/5', false ],
			'LMS API lesson route'           => [ '/lms/v1/lessons/5', false ],
			'post type description (types)'  => [ '/wp/v2/types/lesson', false ],
		];
	}

	public function test_matches_nothing_when_lessons_are_not_in_rest(): void {
		$this->assertFalse( LessonRestGuard::matches( '/wp/v2/lesson/5', '' ) );
	}
}
