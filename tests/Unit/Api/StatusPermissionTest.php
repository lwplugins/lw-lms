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
}
