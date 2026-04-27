<?php
/**
 * Progress Repository.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * Handles CRUD operations for progress data.
 */
final class ProgressRepository {

	/**
	 * Get progress for a user and lesson.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return object|null
	 */
	public static function get( int $user_id, int $lesson_id ): ?object {
		global $wpdb;

		$table = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND lesson_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$user_id,
				$lesson_id
			)
		);
	}

	/**
	 * Get all progress for a user in a course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_course_progress( int $user_id, int $course_id ): array {
		global $wpdb;

		$table = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$user_id,
				$course_id
			)
		);

		return $results ? $results : [];
	}

	/**
	 * Get all progress for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_progress( int $user_id ): array {
		global $wpdb;

		$table = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$user_id
			)
		);

		return $results ? $results : [];
	}

	/**
	 * Update or create progress entry.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $course_id Course ID.
	 * @param int    $lesson_id Lesson ID.
	 * @param string $status    Progress status.
	 * @return bool
	 */
	public static function upsert( int $user_id, int $course_id, int $lesson_id, string $status ): bool {
		global $wpdb;

		$table        = ProgressTable::get_table_name();
		$completed_at = 'completed' === $status ? current_time( 'mysql' ) : null;
		$existing     = self::get( $user_id, $lesson_id );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				[
					'status'       => $status,
					'completed_at' => $completed_at,
				],
				[
					'user_id'   => $user_id,
					'lesson_id' => $lesson_id,
				],
				[ '%s', '%s' ],
				[ '%d', '%d' ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$table,
				[
					'user_id'      => $user_id,
					'course_id'    => $course_id,
					'lesson_id'    => $lesson_id,
					'status'       => $status,
					'completed_at' => $completed_at,
				],
				[ '%d', '%d', '%d', '%s', '%s' ]
			);
		}

		if ( false !== $result ) {
			CompletionTracker::maybe_record( $user_id, $course_id, $status );
		}

		return false !== $result;
	}

	/**
	 * Delete progress for a user and lesson.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function delete( int $user_id, int $lesson_id ): bool {
		global $wpdb;

		$table = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table,
			[
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			],
			[ '%d', '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Get completed lesson IDs for a user in a course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return array<int>
	 */
	public static function get_completed_lessons( int $user_id, int $course_id ): array {
		global $wpdb;

		$table = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT lesson_id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'completed'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$user_id,
				$course_id
			)
		);

		return array_map( 'intval', $results );
	}
}
