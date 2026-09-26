<?php
/**
 * Download access decision.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use WP_Error;

/**
 * Decides whether a user may download a file owned by LMS content.
 *
 * Default deny: a file is served only when at least one course or lesson that
 * lists it is readable by the user in its current status and the user has
 * access to it (and, for a lesson, the drip schedule has opened it). Files no
 * course or lesson lists, and files whose owners the user cannot even see,
 * answer 404 so their existence is not disclosed.
 */
final class DownloadAccess {

	/**
	 * Check the owners in order; null means the download is allowed.
	 *
	 * @param array<int, int> $owner_ids Course/lesson IDs, ascending.
	 * @param int             $user_id   User ID (0 = guest).
	 * @return WP_Error|null
	 */
	public static function check( array $owner_ids, int $user_id ): ?WP_Error {
		$first_error = null;

		foreach ( $owner_ids as $owner_id ) {
			$post = get_post( $owner_id );

			if ( ! $post instanceof \WP_Post || ! StatusPermission::can_read_post( $post, $user_id ) ) {
				continue;
			}

			if ( Course::POST_TYPE === $post->post_type ) {
				$error = self::course_error( $post->ID, $user_id );
			} elseif ( Lesson::POST_TYPE === $post->post_type ) {
				$error = self::lesson_error( $post->ID, $user_id );
			} else {
				continue;
			}

			if ( false === $error ) {
				continue;
			}

			if ( null === $error ) {
				return null;
			}

			$first_error = $first_error ?? $error;
		}

		return $first_error ?? self::not_found();
	}

	/**
	 * The 404 for files that are not (visibly) LMS content.
	 *
	 * @return WP_Error
	 */
	public static function not_found(): WP_Error {
		return new WP_Error(
			'not_found',
			__( 'File not found.', 'lw-lms' ),
			[ 'status' => 404 ]
		);
	}

	/**
	 * Error for a course owner (null = allowed).
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return WP_Error|null
	 */
	private static function course_error( int $course_id, int $user_id ): ?WP_Error {
		return AccessChecker::has_course_access( $course_id, $user_id )
			? null
			: self::access_error( $course_id, $user_id );
	}

	/**
	 * Error for a lesson owner (null = allowed, false = skip this owner).
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID.
	 * @return WP_Error|null|false
	 */
	private static function lesson_error( int $lesson_id, int $user_id ) {
		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );
		$course    = $course_id ? get_post( $course_id ) : null;

		// A lesson without a readable course is not visible content.
		if ( ! $course instanceof \WP_Post || Course::POST_TYPE !== $course->post_type || ! StatusPermission::can_read_post( $course, $user_id ) ) {
			return false;
		}

		if ( ! AccessChecker::has_lesson_access( $lesson_id, $user_id ) ) {
			return self::access_error( $course_id, $user_id );
		}

		return LessonLockError::check( $lesson_id, $user_id );
	}

	/**
	 * Access denied error with purchase info.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return WP_Error
	 */
	private static function access_error( int $course_id, int $user_id ): WP_Error {
		if ( ! $user_id ) {
			return new WP_Error(
				'unauthorized',
				__( 'Authentication required.', 'lw-lms' ),
				[ 'status' => 401 ]
			);
		}

		return new WP_Error(
			'forbidden',
			__( 'You do not have access to this content.', 'lw-lms' ),
			array_merge( [ 'status' => 403 ], AccessChecker::get_access_info( $course_id, $user_id ) )
		);
	}
}
