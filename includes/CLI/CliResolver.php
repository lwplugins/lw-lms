<?php
/**
 * CLI argument resolver helpers.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Resolve CLI references (IDs, slugs, logins, emails) to internal IDs.
 *
 * Each method calls WP_CLI::error() on failure, which halts the command —
 * callers receive a guaranteed-non-zero ID.
 */
final class CliResolver {

	/**
	 * Resolve a course reference (ID or slug) to a course post ID.
	 *
	 * @param int|string $ref Course ID or slug.
	 * @return int
	 */
	public static function course_id( int|string $ref ): int {
		return self::post_id( Course::POST_TYPE, $ref, 'Course' );
	}

	/**
	 * Resolve a lesson reference (ID or slug) to a lesson post ID.
	 *
	 * @param int|string $ref Lesson ID or slug.
	 * @return int
	 */
	public static function lesson_id( int|string $ref ): int {
		return self::post_id( Lesson::POST_TYPE, $ref, 'Lesson' );
	}

	/**
	 * Resolve a user reference (ID, login, or email) to a user ID.
	 *
	 * @param int|string $ref User ID, login, or email.
	 * @return int
	 */
	public static function user_id( int|string $ref ): int {
		if ( is_numeric( $ref ) && (int) $ref > 0 ) {
			$user = get_user_by( 'id', (int) $ref );
		} else {
			$user = get_user_by( 'login', (string) $ref );
			if ( ! $user ) {
				$user = get_user_by( 'email', (string) $ref );
			}
		}

		if ( ! $user ) {
			\WP_CLI::error( sprintf( 'User not found: %s', $ref ) );
		}

		return (int) $user->ID;
	}

	/**
	 * Resolve a post reference to a post ID for a given post type.
	 *
	 * @param string     $type  Post type slug.
	 * @param int|string $ref   ID or slug.
	 * @param string     $label Human label for the error message.
	 * @return int
	 */
	private static function post_id( string $type, int|string $ref, string $label ): int {
		if ( is_numeric( $ref ) && (int) $ref > 0 ) {
			$post = get_post( (int) $ref );
		} else {
			$matches = get_posts(
				[
					'name'        => sanitize_title( (string) $ref ),
					'post_type'   => $type,
					'post_status' => 'any',
					'numberposts' => 1,
				]
			);
			$post    = $matches[0] ?? null;
		}

		if ( ! $post || $post->post_type !== $type ) {
			\WP_CLI::error( sprintf( '%s not found: %s', $label, $ref ) );
		}

		return (int) $post->ID;
	}
}
