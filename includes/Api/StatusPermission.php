<?php
/**
 * REST post-status visibility rules.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Decides whether the current user may read courses/lessons in a given
 * post status over REST (follows the core WP_REST_Posts_Controller model).
 *
 * - publish            → everyone
 * - private            → read_private_{courses|lessons}
 * - draft/pending/future and the 'any' query pseudo-status
 *                      → edit_{courses|lessons}
 * - anything else (trash, auto-draft, …) → nobody
 */
final class StatusPermission {

	/**
	 * Statuses gated by the edit_* capability.
	 */
	private const EDIT_STATUSES = [ 'draft', 'pending', 'future', 'any' ];

	/**
	 * Capability suffix per post type.
	 */
	private const CAP_PLURALS = [
		Course::POST_TYPE => 'courses',
		Lesson::POST_TYPE => 'lessons',
	];

	/**
	 * Whether the current user may read posts of this type in this status.
	 *
	 * @param string $status    Post status, or 'any'.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function can_read( string $status, string $post_type ): bool {
		if ( 'publish' === $status ) {
			return true;
		}

		$capability = self::required_capability( $status, $post_type );

		return null !== $capability && current_user_can( $capability );
	}

	/**
	 * Whether a user may read one course or lesson in its current status.
	 *
	 * Per-post variant of can_read() for a given user (not only the current
	 * one), built on the core meta capabilities: a private post needs
	 * read_post, a draft/pending/future post needs edit_post. Those map
	 * through the post type's own capabilities, so authors keep access to
	 * their own drafts. Other statuses (trash, auto-draft, …) and other post
	 * types are never readable here.
	 *
	 * @param \WP_Post $post    Course or lesson.
	 * @param int      $user_id User ID (0 = guest).
	 * @return bool
	 */
	public static function can_read_post( \WP_Post $post, int $user_id ): bool {
		if ( ! isset( self::CAP_PLURALS[ $post->post_type ] ) ) {
			return false;
		}

		if ( 'publish' === $post->post_status ) {
			return true;
		}

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( 'private' === $post->post_status ) {
			return user_can( $user_id, 'read_post', $post->ID );
		}

		if ( in_array( $post->post_status, [ 'draft', 'pending', 'future' ], true ) ) {
			return user_can( $user_id, 'edit_post', $post->ID );
		}

		return false;
	}

	/**
	 * Capability needed for a non-published status (null = never readable).
	 *
	 * @param string $status    Post status, or 'any'.
	 * @param string $post_type Post type.
	 * @return string|null
	 */
	private static function required_capability( string $status, string $post_type ): ?string {
		$plural = self::CAP_PLURALS[ $post_type ] ?? null;

		if ( null === $plural ) {
			return null;
		}

		if ( 'private' === $status ) {
			return 'read_private_' . $plural;
		}

		return in_array( $status, self::EDIT_STATUSES, true ) ? 'edit_' . $plural : null;
	}
}
