<?php
/**
 * Progress Calculator.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Calculates course progress percentages.
 *
 * Issue #7 (1.2.14): course completion is now lock-on-complete. The first
 * time a user reaches 100% in a course, CompletionTracker writes a snapshot
 * with the lesson count at that moment. Subsequent calls use that frozen
 * total, so adding a lesson to the course later does NOT push completed
 * users back below 100%. New (still-in-progress) users see the current
 * lesson count.
 */
final class ProgressCalculator {

	/**
	 * Calculate progress percentage for a user in a course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return array{completed_lessons: int, total_lessons: int, percentage: int}
	 */
	public static function calculate( int $user_id, int $course_id ): array {
		$completed_lessons = count( ProgressQueries::get_completed_lessons( $user_id, $course_id ) );
		$total_lessons     = self::resolve_total_lessons( $user_id, $course_id, $completed_lessons );

		$percentage = 0;
		if ( $total_lessons > 0 ) {
			$percentage = (int) round( ( $completed_lessons / $total_lessons ) * 100 );
		}

		return [
			'completed_lessons' => $completed_lessons,
			'total_lessons'     => $total_lessons,
			'percentage'        => min( 100, $percentage ),
		];
	}

	/**
	 * Total lessons used for the percentage. Honours an existing completion
	 * snapshot (frozen total) and falls back to the current course size for
	 * users who have not yet completed.
	 *
	 * @param int $user_id           User id.
	 * @param int $course_id         Course id.
	 * @param int $completed_lessons Number of lessons the user has completed.
	 * @return int
	 */
	private static function resolve_total_lessons( int $user_id, int $course_id, int $completed_lessons ): int {
		$snapshot = ProgressSnapshotRepository::get( $user_id, $course_id );

		if ( null !== $snapshot ) {
			// Defensive: never report fewer total than completed (would push
			// percentage above 100% if a snapshot ever drifted).
			return max( (int) $snapshot->total_lessons, $completed_lessons );
		}

		return self::get_total_lessons( $course_id );
	}

	/**
	 * Get total number of lessons in a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function get_total_lessons( int $course_id ): int {
		$lessons = get_posts(
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

		return count( $lessons );
	}

	/**
	 * Check if a course is completed by user.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_course_completed( int $user_id, int $course_id ): bool {
		$progress = self::calculate( $user_id, $course_id );
		return 100 === $progress['percentage'];
	}

	/**
	 * Check if a lesson is completed by user.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function is_lesson_completed( int $user_id, int $lesson_id ): bool {
		$progress = ProgressQueries::get( $user_id, $lesson_id );
		return $progress && 'completed' === $progress->status;
	}
}
