<?php
/**
 * Backfill attempt rows for submissions made before the table existed.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;

/**
 * 1.8.0 stored only a summary of the last attempt in user meta. This lifts
 * each of those into the attempt table so the history does not start empty.
 *
 * The answer snapshot is unknown for those (it was never stored), so the row
 * keeps `answers` NULL. Idempotent: a user/lesson pair that already has a row
 * is skipped, so it is safe to run on every activation.
 */
final class QuizAttemptMigration {

	/**
	 * Lift the 1.8.0 summaries into the attempt table.
	 *
	 * @return int Number of rows written.
	 */
	public static function backfill(): int {
		global $wpdb;

		$prefix = Options::META_PREFIX . 'quiz_';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$written = 0;

		foreach ( $rows as $row ) {
			$lesson_id = (int) substr( (string) $row->meta_key, strlen( $prefix ) );
			$user_id   = (int) $row->user_id;
			$summary   = maybe_unserialize( $row->meta_value );

			if ( $lesson_id <= 0 || $user_id <= 0 || ! is_array( $summary ) ) {
				continue;
			}

			if ( null !== QuizAttemptQueries::latest( $user_id, $lesson_id ) ) {
				continue;
			}

			$rows = self::insert( $user_id, $lesson_id, $summary );

			if ( $rows > 0 ) {
				self::seed_totals( $user_id, $lesson_id, $summary );
			}

			$written += $rows;
		}

		return $written;
	}

	/**
	 * Teach the 1.8.0 summary the counters it never had, so the next
	 * submission continues from 1 attempt instead of resetting to it.
	 *
	 * @param int                  $user_id   User ID.
	 * @param int                  $lesson_id Lesson ID.
	 * @param array<string, mixed> $summary   Stored summary.
	 * @return void
	 */
	private static function seed_totals( int $user_id, int $lesson_id, array $summary ): void {
		$summary['attempts']        = 1;
		$summary['best_percentage'] = (float) ( $summary['percentage'] ?? 0 );

		update_user_meta( $user_id, QuizAttempts::meta_key( $lesson_id ), $summary );
	}

	/**
	 * Write one legacy summary as an attempt row.
	 *
	 * @param int                  $user_id   User ID.
	 * @param int                  $lesson_id Lesson ID.
	 * @param array<string, mixed> $summary   Stored summary.
	 * @return int 1 when a row was written.
	 */
	private static function insert( int $user_id, int $lesson_id, array $summary ): int {
		global $wpdb;

		$submitted_at = (string) ( $summary['submitted_at'] ?? $summary['passed_at'] ?? '' );

		if ( '' === $submitted_at ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			QuizAttemptTable::get_table_name(),
			[
				'user_id'          => $user_id,
				'lesson_id'        => $lesson_id,
				'course_id'        => (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 ),
				'percentage'       => (float) ( $summary['percentage'] ?? 0 ),
				'score'            => 0,
				'scored_questions' => 0,
				'passed'           => ! empty( $summary['passed'] ) ? 1 : 0,
				'answers'          => null,
				'submitted_at'     => $submitted_at,
			],
			[ '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%s', '%s' ]
		);

		return $inserted ? 1 : 0;
	}
}
