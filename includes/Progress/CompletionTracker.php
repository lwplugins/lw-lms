<?php
/**
 * Detects when a user reaches 100% in a course and writes a snapshot
 * so that subsequent lesson additions cannot retroactively un-complete them.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * One responsibility: "did this lesson update finish the course?".
 *
 * Called by ProgressRepository::upsert() after every status change. Cheap to
 * call repeatedly because record() is idempotent (UNIQUE KEY user_course in
 * the snapshot table prevents double-writes).
 */
final class CompletionTracker {

	/**
	 * Run after a lesson's status changed. If the user is now at 100% in the
	 * course, persist the snapshot.
	 *
	 * @param int    $user_id   User id.
	 * @param int    $course_id Course id.
	 * @param string $status    Newly written status.
	 * @return void
	 */
	public static function maybe_record( int $user_id, int $course_id, string $status ): void {
		if ( 'completed' !== $status ) {
			return;
		}

		if ( ProgressSnapshotRepository::exists( $user_id, $course_id ) ) {
			return;
		}

		$total     = ProgressCalculator::get_total_lessons( $course_id );
		$completed = count( ProgressRepository::get_completed_lessons( $user_id, $course_id ) );

		if ( $total <= 0 || $completed < $total ) {
			return;
		}

		ProgressSnapshotRepository::record( $user_id, $course_id, $total );
	}
}
