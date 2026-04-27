<?php
/**
 * Course completion snapshot repository.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * CRUD over `wp_lms_completion_snapshots`.
 */
final class ProgressSnapshotRepository {

	/**
	 * Read the snapshot for a user × course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return object|null Row with `total_lessons` and `completed_at`, or null.
	 */
	public static function get( int $user_id, int $course_id ): ?object {
		global $wpdb;
		$table = ProgressSnapshotTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT total_lessons, completed_at FROM {$table} WHERE user_id = %d AND course_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$user_id,
				$course_id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Whether a user has a completion snapshot for the given course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function exists( int $user_id, int $course_id ): bool {
		return null !== self::get( $user_id, $course_id );
	}

	/**
	 * Insert (or refresh) a completion snapshot for the user × course pair.
	 *
	 * Idempotent: if a snapshot already exists, it stays — completion is a
	 * one-shot event and we do not overwrite the original total even if the
	 * course's lesson count later changes.
	 *
	 * @param int $user_id       User id.
	 * @param int $course_id     Course id.
	 * @param int $total_lessons Total lessons at the moment of completion.
	 * @return bool True when a row was inserted.
	 */
	public static function record( int $user_id, int $course_id, int $total_lessons ): bool {
		if ( $total_lessons <= 0 ) {
			return false;
		}

		if ( self::exists( $user_id, $course_id ) ) {
			return false;
		}

		global $wpdb;
		$table = ProgressSnapshotTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			[
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'total_lessons' => $total_lessons,
				'completed_at'  => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a snapshot. Useful when an admin manually resets a user's progress.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function delete( int $user_id, int $course_id ): bool {
		global $wpdb;
		$table = ProgressSnapshotTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			[
				'user_id'   => $user_id,
				'course_id' => $course_id,
			],
			[ '%d', '%d' ]
		);

		return false !== $result;
	}
}
