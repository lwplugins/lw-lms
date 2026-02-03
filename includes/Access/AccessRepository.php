<?php
/**
 * Access Repository.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Handles CRUD operations for the access table.
 */
final class AccessRepository {

	/**
	 * Check if a user has active access to a course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function has_active_access( int $user_id, int $course_id ): bool {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
				$user_id,
				$course_id,
				current_time( 'mysql' )
			)
		);

		return null !== $result;
	}

	/**
	 * Grant access to a user for a course.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param string      $source     Access source (woocommerce, manual, subscription).
	 * @param int|null    $source_id  Source ID (e.g., order ID).
	 * @param string|null $expires_at Expiration datetime or null for unlimited.
	 * @return bool
	 */
	public static function grant(
		int $user_id,
		int $course_id,
		string $source = 'manual',
		?int $source_id = null,
		?string $expires_at = null
	): bool {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// Check for existing record with same user, course, and source_id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND source_id = %d LIMIT 1",
				$user_id,
				$course_id,
				$source_id ? $source_id : 0
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				[
					'status'     => 'active',
					'granted_at' => current_time( 'mysql' ),
					'expires_at' => $expires_at,
				],
				[ 'id' => $existing ],
				[ '%s', '%s', '%s' ],
				[ '%d' ]
			);

			return false !== $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			[
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'source'     => $source,
				'source_id'  => $source_id,
				'granted_at' => current_time( 'mysql' ),
				'expires_at' => $expires_at,
				'status'     => 'active',
			],
			[ '%d', '%d', '%s', '%d', '%s', '%s', '%s' ]
		);

		return false !== $result;
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

	/**
	 * Revoke access for a user and course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function revoke( int $user_id, int $course_id ): bool {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[ 'status' => 'revoked' ],
			[
				'user_id'   => $user_id,
				'course_id' => $course_id,
				'status'    => 'active',
			],
			[ '%s' ],
			[ '%d', '%d', '%s' ]
		);

		return false !== $result;
	}
}
