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
