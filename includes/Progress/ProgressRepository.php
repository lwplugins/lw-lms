<?php
/**
 * Progress Repository.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Write operations for the progress table.
 *
 * Read queries live in ProgressQueries.
 */
final class ProgressRepository {

	/**
	 * Update or create progress entry.
	 *
	 * @since 1.3.0 Fires lw_lms_lesson_completed when status transitions to completed.
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
		$existing     = ProgressQueries::get( $user_id, $lesson_id );
		$was_complete = $existing && 'completed' === $existing->status;

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

		if ( false === $result ) {
			return false;
		}

		if ( 'completed' === $status && ! $was_complete ) {
			/**
			 * Fires when a lesson transitions to completed.
			 *
			 * @since 1.2.0
			 *
			 * @param int $lesson_id Lesson ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'lw_lms_lesson_completed', $lesson_id, $user_id );
		}

		CompletionTracker::maybe_record( $user_id, $course_id, $status );

		return true;
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
	 * Mark all lessons in a course as completed for a user.
	 *
	 * Enumerates published lessons assigned to the course and upserts each as
	 * completed. The final upsert triggers CompletionTracker::maybe_record(),
	 * which fires lw_lms_course_completed once the snapshot is written.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool True if at least one lesson was upserted.
	 */
	public static function mark_course_completed( int $user_id, int $course_id ): bool {
		$lesson_ids = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		if ( empty( $lesson_ids ) ) {
			return false;
		}

		$any = false;

		foreach ( $lesson_ids as $lesson_id ) {
			if ( self::upsert( $user_id, $course_id, (int) $lesson_id, 'completed' ) ) {
				$any = true;
			}
		}

		return $any;
	}
}
