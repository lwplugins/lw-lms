<?php
/**
 * Tests for the core REST lesson route guard.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\LessonRestGuard;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: published lessons (paid content included) were readable by
 * anyone through /wp/v2/lesson, bypassing the /lms/v1 access gate.
 *
 * @covers \LightweightPlugins\LMS\Api\LessonRestGuard
 */
final class LessonRestGuardTest extends MonkeyTestCase {

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

	/**
	 * Grant exactly these capabilities (meta caps as "cap:id").
	 *
	 * @param array<int, string> $caps Capabilities.
	 */
	private function caps( array $caps ): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap, mixed $id = null ): bool => in_array( null === $id ? $cap : $cap . ':' . $id, $caps, true )
		);
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'cap' => (object) [
					'edit_others_posts' => 'edit_others_posts',
					'create_posts'      => 'edit_posts',
				],
			]
		);
	}

	public function test_contributor_with_edit_posts_cannot_read_a_published_lesson(): void {
		$this->caps( [ 'edit_posts' ] );

		$this->assertFalse( LessonRestGuard::allows( 'GET', '/wp/v2/lesson/5', '/wp/v2/lesson' ) );
		$this->assertFalse( LessonRestGuard::allows( 'GET', '/wp/v2/lesson/5/revisions', '/wp/v2/lesson' ) );
	}

	public function test_edit_posts_alone_cannot_list_lessons(): void {
		$this->caps( [ 'edit_posts' ] );

		$this->assertFalse( LessonRestGuard::allows( 'GET', '/wp/v2/lesson', '/wp/v2/lesson' ) );
	}

	public function test_user_who_can_edit_the_lesson_may_use_its_routes(): void {
		$this->caps( [ 'edit_posts', 'edit_post:5' ] );

		$this->assertTrue( LessonRestGuard::allows( 'GET', '/wp/v2/lesson/5', '/wp/v2/lesson' ) );
		$this->assertTrue( LessonRestGuard::allows( 'POST', '/wp/v2/lesson/5/autosaves', '/wp/v2/lesson' ) );
		$this->assertFalse( LessonRestGuard::allows( 'GET', '/wp/v2/lesson/6', '/wp/v2/lesson' ) );
	}

	public function test_editor_may_list_and_author_may_create(): void {
		$this->caps( [ 'edit_posts', 'edit_others_posts' ] );
		$this->assertTrue( LessonRestGuard::allows( 'GET', '/wp/v2/lesson', '/wp/v2/lesson' ) );

		$this->caps( [ 'edit_posts' ] );
		$this->assertTrue( LessonRestGuard::allows( 'POST', '/wp/v2/lesson', '/wp/v2/lesson' ) );
	}

	public function test_manage_lms_may_use_every_route(): void {
		$this->caps( [ 'manage_lms' ] );

		$this->assertTrue( LessonRestGuard::allows( 'GET', '/wp/v2/lesson/5', '/wp/v2/lesson' ) );
		$this->assertTrue( LessonRestGuard::allows( 'GET', '/wp/v2/lesson', '/wp/v2/lesson' ) );
	}

	public function test_lesson_id_is_read_from_single_routes_only(): void {
		$this->assertSame( 5, LessonRestGuard::lesson_id( '/wp/v2/lesson/5', '/wp/v2/lesson' ) );
		$this->assertSame( 5, LessonRestGuard::lesson_id( '/wp/v2/LESSON/5/revisions/9', '/wp/v2/lesson' ) );
		$this->assertSame( 0, LessonRestGuard::lesson_id( '/wp/v2/lesson', '/wp/v2/lesson' ) );
		$this->assertSame( 0, LessonRestGuard::lesson_id( '/wp/v2/lesson/', '/wp/v2/lesson' ) );
	}
}
