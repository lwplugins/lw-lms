<?php
/**
 * Backfill completion snapshots for users who already finished a course
 * before 1.2.14 introduced the snapshot table.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * One-shot migration. Idempotent — safe to call on every activation, since
 * `ProgressSnapshotRepository::record()` skips pairs that already have a row.
 */
final class ProgressSnapshotMigration {

	/**
	 * Walk every (user_id, course_id) pair in `wp_lms_progress` that has a
	 * completed status, and write a snapshot capturing the course's current
	 * lesson count. Anyone already at 100% gets locked in.
	 *
	 * @return int Number of snapshots written.
	 */
	public static function backfill(): int {
		global $wpdb;
		$progress = ProgressTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pairs = $wpdb->get_results(
			"SELECT DISTINCT user_id, course_id FROM {$progress} WHERE status = 'completed'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
			ARRAY_A
		);

		if ( ! is_array( $pairs ) ) {
			return 0;
		}

		$written = 0;

		foreach ( $pairs as $pair ) {
			$user_id   = (int) ( $pair['user_id'] ?? 0 );
			$course_id = (int) ( $pair['course_id'] ?? 0 );

			if ( $user_id <= 0 || $course_id <= 0 ) {
				continue;
			}

			if ( ProgressSnapshotRepository::exists( $user_id, $course_id ) ) {
				continue;
			}

			$total     = ProgressCalculator::get_total_lessons( $course_id );
			$completed = count( ProgressRepository::get_completed_lessons( $user_id, $course_id ) );

			if ( $total <= 0 || $completed < $total ) {
				continue;
			}

			if ( ProgressSnapshotRepository::record( $user_id, $course_id, $total ) ) {
				++$written;
			}
		}

		return $written;
	}
}
