<?php
/**
 * Per-learner, per-lesson lock around a quiz submission.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Makes the throttle check and the recording of an attempt one atomic step.
 *
 * QuizThrottle reads the last attempts, QuizSubmission then stores the new
 * one. Without a lock, parallel requests all pass the check before any of
 * them is stored, and skip the cooldown and the daily cap. A MySQL advisory
 * lock (GET_LOCK, no waiting) per learner and lesson lets one submission in
 * at a time: a parallel one is refused, a later one sees the stored attempt.
 *
 * GET_LOCK names are server-wide, so the name carries a hash of the database
 * name and table prefix. When the database has no GET_LOCK (it returns
 * NULL), submissions go through unlocked, as before.
 */
final class QuizSubmitLock {

	/**
	 * Try to take the lock.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool|null True when taken, false when another submission holds
	 *                   it, null when the database does not support locks.
	 */
	public static function acquire( int $user_id, int $lesson_id ): ?bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock, not data.
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::name( $user_id, $lesson_id ) ) );

		if ( null === $result ) {
			return null;
		}

		return '1' === (string) $result;
	}

	/**
	 * Release the lock.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return void
	 */
	public static function release( int $user_id, int $lesson_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock, not data.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::name( $user_id, $lesson_id ) ) );
	}

	/**
	 * Lock name, at most 64 characters (MySQL's limit).
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return string
	 */
	public static function name( int $user_id, int $lesson_id ): string {
		global $wpdb;

		$scope = substr( md5( ( defined( 'DB_NAME' ) ? (string) DB_NAME : '' ) . '|' . (string) $wpdb->prefix ), 0, 12 );

		return substr( 'lwlms_quiz_' . $scope . '_' . $user_id . '_' . $lesson_id, 0, 64 );
	}
}
