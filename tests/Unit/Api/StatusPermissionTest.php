<?php
/**
 * Tests for REST post-status visibility rules.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\StatusPermission;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Api\StatusPermission
 */
final class StatusPermissionTest extends MonkeyTestCase {

	/**
	 * @dataProvider provide_status_rules
	 */
	public function test_status_readability_depends_on_matching_capability(
		string $status,
		string $post_type,
		string $granted_cap,
		bool $expected
	): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $cap ): bool => $cap === $granted_cap
		);

		$this->assertSame( $expected, StatusPermission::can_read( $status, $post_type ) );
	}

	public static function provide_status_rules(): array {
		return [
			'publish course, no caps'           => [ 'publish', 'course', '', true ],
			'private course, read_private'      => [ 'private', 'course', 'read_private_courses', true ],
			'private course, edit only'         => [ 'private', 'course', 'edit_courses', false ],
			'private course, no caps'           => [ 'private', 'course', '', false ],
			'draft course, edit_courses'        => [ 'draft', 'course', 'edit_courses', true ],
			'draft course, read_private only'   => [ 'draft', 'course', 'read_private_courses', false ],
			'pending course, edit_courses'      => [ 'pending', 'course', 'edit_courses', true ],
			'future course, edit_courses'       => [ 'future', 'course', 'edit_courses', true ],
			'any courses, edit_courses'         => [ 'any', 'course', 'edit_courses', true ],
			'any courses, no caps'              => [ 'any', 'course', '', false ],
			'trash course, edit_courses'        => [ 'trash', 'course', 'edit_courses', false ],
			'auto-draft course, edit_courses'   => [ 'auto-draft', 'course', 'edit_courses', false ],
			'draft lesson, edit_lessons'        => [ 'draft', 'lesson', 'edit_lessons', true ],
			'draft lesson, edit_courses only'   => [ 'draft', 'lesson', 'edit_courses', false ],
			'private lesson, read_private'      => [ 'private', 'lesson', 'read_private_lessons', true ],
			'draft of unknown type, edit_posts' => [ 'draft', 'post', 'edit_posts', false ],
		];
	}

	/**
	 * @dataProvider provide_post_rules
	 */
	public function test_post_readability_uses_meta_caps_for_the_given_user(
		string $status,
		string $post_type,
		int $user_id,
		string $granted_cap,
		bool $expected
	): void {
		Functions\when( 'user_can' )->alias(
			static fn ( int $user, string $cap, int $post ): bool => $user === $user_id && $cap === $granted_cap && 5 === $post
		);

		$post = new \WP_Post(
			[
				'ID'          => 5,
				'post_type'   => $post_type,
				'post_status' => $status,
			]
		);

		$this->assertSame( $expected, StatusPermission::can_read_post( $post, $user_id ) );
	}

	public static function provide_post_rules(): array {
		return [
			'published course, guest'        => [ 'publish', 'course', 0, '', true ],
			'published lesson, guest'        => [ 'publish', 'lesson', 0, '', true ],
			'published page is not LMS'      => [ 'publish', 'page', 0, '', false ],
			'draft course, guest'            => [ 'draft', 'course', 0, 'edit_post', false ],
			'draft course, editor'           => [ 'draft', 'course', 3, 'edit_post', true ],
			'draft course, reader only'      => [ 'draft', 'course', 3, 'read_post', false ],
			'pending lesson, editor'         => [ 'pending', 'lesson', 3, 'edit_post', true ],
			'future lesson, no cap'          => [ 'future', 'lesson', 3, '', false ],
			'private course, read_post'      => [ 'private', 'course', 3, 'read_post', true ],
			'private course, no cap'         => [ 'private', 'course', 3, '', false ],
			'trash course, edit_post'        => [ 'trash', 'course', 3, 'edit_post', false ],
			'auto-draft lesson, edit_post'   => [ 'auto-draft', 'lesson', 3, 'edit_post', false ],
		];
	}
}
