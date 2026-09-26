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
use LightweightPlugins\LMS\SiteManager\Service\CourseService;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the route's per_page default (10) always won, so the
 * courses_per_page setting was never used.
 *
 * Regression: draft and private courses were gated only by the type-wide
 * edit_posts capability, so a Contributor could list and read everyone's
 * unpublished courses.
 *
 * @covers \LightweightPlugins\LMS\Api\Controllers\CoursesController
 * @covers \LightweightPlugins\LMS\SiteManager\Service\CourseService
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

	/**
	 * A course post.
	 *
	 * @param int    $id     Post ID.
	 * @param string $status Post status.
	 * @return \WP_Post
	 */
	private function course( int $id, string $status ): \WP_Post {
		return new \WP_Post(
			[
				'ID'          => $id,
				'post_type'   => 'course',
				'post_status' => $status,
			]
		);
	}

	/**
	 * User 5 has edit_posts (a Contributor) but may edit only post 2
	 * (their own draft).
	 *
	 * @return void
	 */
	private function contributor(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 5 );
		Functions\when( 'current_user_can' )->alias( static fn ( string $cap ): bool => 'edit_posts' === $cap );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'cap' => (object) [
					'edit_posts'         => 'edit_posts',
					'read_private_posts' => 'read_private_posts',
				],
			]
		);
		Functions\when( 'user_can' )->alias(
			static fn ( int $user, string $cap, int $post_id ): bool => 'edit_post' === $cap && 2 === $post_id
		);
	}

	public function test_list_keeps_only_posts_the_user_may_read(): void {
		$this->contributor();
		$posts = [ $this->course( 1, 'publish' ), $this->course( 2, 'draft' ), $this->course( 3, 'draft' ), $this->course( 4, 'private' ) ];

		$ids = array_map( static fn ( \WP_Post $p ): int => $p->ID, CoursesController::readable( $posts, 5 ) );

		$this->assertSame( [ 1, 2 ], $ids );
	}

	public function test_single_draft_of_another_user_is_not_found(): void {
		$this->contributor();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_post' )->justReturn( $this->course( 3, 'draft' ) );

		$result = ( new CoursesController() )->get_course( new \WP_REST_Request( [ 'id' => 3 ] ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_site_manager_get_course_hides_unreadable_drafts(): void {
		$this->contributor();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_post' )->justReturn( $this->course( 3, 'draft' ) );

		$result = CourseService::get_course( [ 'course_id' => 3 ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}
}
