<?php
/**
 * Progress Queries.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * Read-only queries against the progress table.
 */
final class ProgressQueries {

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
