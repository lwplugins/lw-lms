<?php
/**
 * Access Repository.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Write operations for the access table (grant/revoke).
 *
 * Read queries live in AccessQueries.
 */
final class AccessRepository {

	/**
	 * Grant access to a user for a course.
	 *
	 * @since 1.3.0 Added lw_lms_pre_grant filter and lw_lms_after_grant action.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param string      $source     Access source (woocommerce, manual, subscription, free).
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
		/**
		 * Filter whether to allow granting access. Return false to abort.
		 *
		 * Callers must register with $accepted_args = 6, otherwise the user/course
		 * arguments are silently dropped.
		 *
		 * @since 1.3.0
		 *
		 * @param bool        $allow      Whether to allow the grant.
		 * @param int         $user_id    User ID.
		 * @param int         $course_id  Course ID.
		 * @param string      $source     Access source.
		 * @param int|null    $source_id  Source ID.
		 * @param string|null $expires_at Expiration datetime.
		 */
		$allow = apply_filters( 'lw_lms_pre_grant', true, $user_id, $course_id, $source, $source_id, $expires_at );

		if ( ! $allow ) {
			return false;
		}

		global $wpdb;

		$table = AccessTable::get_table_name();

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
		} else {
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
		}

		if ( false === $result ) {
			return false;
		}

		/**
		 * Fires after access is granted (insert or update).
		 *
		 * Callers must register with $accepted_args = 5.
		 *
		 * @since 1.3.0
		 *
		 * @param int         $user_id    User ID.
		 * @param int         $course_id  Course ID.
		 * @param string      $source     Access source.
		 * @param int|null    $source_id  Source ID.
		 * @param string|null $expires_at Expiration datetime, null = unlimited.
		 */
		do_action( 'lw_lms_after_grant', $user_id, $course_id, $source, $source_id, $expires_at );

		return true;
	}

	/**
	 * Revoke access for a user and course.
	 *
	 * Fires lw_lms_after_revoke only when an active row was actually flipped.
	 *
	 * @since 1.3.0 Added lw_lms_after_revoke action.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function revoke( int $user_id, int $course_id ): bool {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT id, source FROM {$table} WHERE user_id = %d AND course_id = %d AND status = 'active' LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( ! $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			[ 'status' => 'revoked' ],
			[ 'id' => (int) $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $result || 0 === (int) $result ) {
			return false;
		}

		/**
		 * Fires after access is revoked.
		 *
		 * Callers must register with $accepted_args = 3.
		 *
		 * @since 1.3.0
		 *
		 * @param int    $user_id   User ID.
		 * @param int    $course_id Course ID.
		 * @param string $source    Access source of the revoked row.
		 */
		do_action( 'lw_lms_after_revoke', $user_id, $course_id, (string) $row->source );

		return true;
	}
}
