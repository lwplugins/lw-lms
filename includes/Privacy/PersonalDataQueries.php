<?php
/**
 * Personal data reads and deletes.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Privacy;

use LightweightPlugins\LMS\Access\AccessTable;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Progress\ProgressSnapshotTable;
use LightweightPlugins\LMS\Progress\ProgressTable;
use LightweightPlugins\LMS\Quiz\QuizAttemptTable;

/**
 * The per-user rows of the four LMS tables and the user meta the plugin
 * stores, for the privacy exporter and eraser.
 */
final class PersonalDataQueries {

	/**
	 * Access rows of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object>
	 */
	public static function access( int $user_id ): array {
		return self::rows( AccessTable::get_table_name(), $user_id, 'id' );
	}

	/**
	 * Progress rows of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, object>
	 */
	public static function progress( int $user_id ): array {
		return self::rows( ProgressTable::get_table_name(), $user_id, 'id' );
	}

	/**
	 * Quiz attempts of a user, a page at a time.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Rows per page.
	 * @param int $offset  Rows to skip.
	 * @return array<int, object>
	 */
	public static function quiz_attempts( int $user_id, int $limit, int $offset ): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Delete a user's progress, completion records and quiz attempts, and
	 * the quiz summaries and drip start dates in their user meta.
	 *
	 * @param int $user_id User ID.
	 * @return int Rows removed.
	 */
	public static function delete_learning_data( int $user_id ): int {
		global $wpdb;

		$removed = 0;

		foreach ( [ ProgressTable::get_table_name(), ProgressSnapshotTable::get_table_name(), QuizAttemptTable::get_table_name() ] as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$removed += (int) $wpdb->delete( $table, [ 'user_id' => $user_id ], [ '%d' ] );
		}

		foreach ( [ 'quiz_', 'course_start_' ] as $suffix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$removed += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
					$user_id,
					$wpdb->esc_like( Options::META_PREFIX . $suffix ) . '%'
				)
			);
		}

		wp_cache_delete( $user_id, 'user_meta' );

		return $removed;
	}

	/**
	 * Delete a user's access rows.
	 *
	 * @param int  $user_id     User ID.
	 * @param bool $keep_active Keep active rows (still granting access).
	 * @return int Rows removed.
	 */
	public static function delete_access( int $user_id, bool $keep_active ): int {
		global $wpdb;
		$table = AccessTable::get_table_name();

		if ( ! $keep_active ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->delete( $table, [ 'user_id' => $user_id ], [ '%d' ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"DELETE FROM {$table} WHERE user_id = %d AND ( status <> 'active' OR ( expires_at IS NOT NULL AND expires_at <= %s ) )",
				$user_id,
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * All rows of a user in a table.
	 *
	 * @param string $table   Table name (from a *Table::get_table_name()).
	 * @param int    $user_id User ID.
	 * @param string $order   Order column.
	 * @return array<int, object>
	 */
	private static function rows( string $table, int $user_id, string $order ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, order column is internal.
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY {$order} ASC",
				$user_id
			)
		);

		return is_array( $rows ) ? $rows : [];
	}
}
