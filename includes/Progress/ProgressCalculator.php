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
 * Known limitation (issue #7): if a course gains a new lesson while users are
 * mid-progress, their stored "completed" set is unchanged, so percentage
 * calculations against the new total can shift unexpectedly. LearnDash has the
 * same behaviour. The plugin currently assumes courses are append-only or
 * authored before enrolment; if you need recalculation, hook into
 * `save_post_lesson` and rebuild the progress rows for affected users.
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
		$total_lessons     = self::get_total_lessons( $course_id );
		$completed_lessons = count( ProgressRepository::get_completed_lessons( $user_id, $course_id ) );

		$percentage = 0;
		if ( $total_lessons > 0 ) {
			$percentage = (int) round( ( $completed_lessons / $total_lessons ) * 100 );
		}

		return [
			'completed_lessons' => $completed_lessons,
			'total_lessons'     => $total_lessons,
			'percentage'        => $percentage,
		];
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
		$progress = ProgressRepository::get( $user_id, $lesson_id );
		return $progress && 'completed' === $progress->status;
	}
}
