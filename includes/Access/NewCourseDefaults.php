<?php
/**
 * Defaults for new courses.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Gives every new course the "Default access type" from the settings.
 *
 * Applied once, when the course post is first inserted (the auto-draft of the
 * editor, a REST create or `wp lw-lms course create`), so the access metabox
 * opens with the configured type. Courses that already exist keep what they
 * have; a course with no stored type still reads as free.
 */
final class NewCourseDefaults {

	/**
	 * Access types a course can have.
	 */
	public const ACCESS_TYPES = [
		AccessChecker::ACCESS_OPEN,
		AccessChecker::ACCESS_FREE,
		AccessChecker::ACCESS_PAID,
	];

	/**
	 * Hook the default.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'save_post_' . Course::POST_TYPE, [ self::class, 'apply' ], 5, 3 );
	}

	/**
	 * Store the default access type on a newly inserted course.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Whether this is an update of an existing post.
	 * @return void
	 */
	public static function apply( int $post_id, \WP_Post $post, bool $update ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- save_post signature.
		if ( $update || metadata_exists( 'post', $post_id, Options::META_PREFIX . 'access_type' ) ) {
			return;
		}

		Options::set_post_meta( $post_id, 'access_type', self::access_type() );
	}

	/**
	 * The configured default access type (free when unset or invalid).
	 *
	 * @return string
	 */
	public static function access_type(): string {
		$type = (string) Options::get( 'default_access_type', AccessChecker::ACCESS_FREE );

		return in_array( $type, self::ACCESS_TYPES, true ) ? $type : AccessChecker::ACCESS_FREE;
	}
}
