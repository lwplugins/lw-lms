<?php
/**
 * Access Queries.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Read-only queries against the access table.
 */
final class AccessQueries {

	/**
	 * Check if a user has active access to a course.
	 *
	 * The optional $source argument restricts the check to a specific access
	 * source (e.g. 'free', 'manual', 'woocommerce', 'subscription'). When
	 * omitted, any active source matches.
	 *
	 * @since 1.3.0 Added optional $source argument.
	 *
	 * @param int         $user_id   User ID.
	 * @param int         $course_id Course ID.
	 * @param string|null $source    Optional source to match.
	 * @return bool
	 */
	public static function has_active_access( int $user_id, int $course_id, ?string $source = null ): bool {
		global $wpdb;

		$table = AccessTable::get_table_name();
		$now   = current_time( 'mysql' );

		if ( null === $source ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
					$user_id,
					$course_id,
					$now
				)
			);

			return null !== $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND source = %s AND status = 'active' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
				$user_id,
				$course_id,
				$source,
				$now
			)
		);

		return null !== $result;
	}

	/**
	 * Get active access record for a user and course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_user_access( int $user_id, int $course_id ): ?object {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'active' ORDER BY expires_at DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Get all enrollments for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object>
	 */
	public static function get_user_enrollments( int $user_id ): array {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY granted_at DESC",
				$user_id
			)
		);

		return is_array( $results ) ? $results : [];
	}
}
