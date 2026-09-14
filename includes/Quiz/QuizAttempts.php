<?php
/**
 * Quiz attempt recording and per-lesson summary.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;

/**
 * Every submission is stored as a row in the attempt table (full history with
 * the answer snapshot). User meta `_lw_lms_quiz_{lesson_id}` keeps a summary
 * of the last attempt, so the lesson payload and the completion gate never
 * have to touch the table.
 *
 * `passed_at` survives a later failed retry, so a learner who already passed
 * is not locked out again.
 */
final class QuizAttempts {

	/**
	 * Record an attempt: one table row plus the refreshed summary.
	 *
	 * @param int                              $user_id   User ID.
	 * @param int                              $lesson_id Lesson ID.
	 * @param int                              $course_id Course ID (0 when unassigned).
	 * @param array<string, mixed>             $result    Scoring result (see QuizScorer::score()).
	 * @param array<int, array<string, mixed>> $snapshot  Answer snapshot.
	 * @return array<string, mixed> The stored summary.
	 */
	public static function record( int $user_id, int $lesson_id, int $course_id, array $result, array $snapshot ): array {
		$now        = current_time( 'mysql' );
		$percentage = (float) $result['percentage'];
		$passed     = (bool) $result['passed'];
		$previous   = self::get( $user_id, $lesson_id );

		QuizAttemptRepository::record( $user_id, $lesson_id, $course_id, $result, $snapshot, $now );

		$summary = [
			'percentage'      => $percentage,
			'passed'          => $passed,
			'submitted_at'    => $now,
			'passed_at'       => $passed ? $now : ( $previous['passed_at'] ?? null ),
			'attempts'        => (int) ( $previous['attempts'] ?? 0 ) + 1,
			'best_percentage' => max( $percentage, (float) ( $previous['best_percentage'] ?? 0 ) ),
		];

		update_user_meta( $user_id, self::meta_key( $lesson_id ), $summary );

		return $summary;
	}

	/**
	 * Get the summary of the last attempt, or null when there is none.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $user_id, int $lesson_id ): ?array {
		$attempt = get_user_meta( $user_id, self::meta_key( $lesson_id ), true );

		return is_array( $attempt ) ? $attempt : null;
	}

	/**
	 * Whether the user has ever passed this lesson's quiz.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function has_passed( int $user_id, int $lesson_id ): bool {
		$attempt = self::get( $user_id, $lesson_id );

		return null !== $attempt && ! empty( $attempt['passed_at'] );
	}

	/**
	 * Summary of the last attempt, extended with the stored answer snapshot so
	 * a learner can review what they got wrong after a page reload.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return array<string, mixed>|null
	 */
	public static function last_with_review( int $user_id, int $lesson_id ): ?array {
		$summary = self::get( $user_id, $lesson_id );

		if ( null === $summary ) {
			return null;
		}

		$row     = QuizAttemptQueries::latest( $user_id, $lesson_id );
		$answers = $row && $row->answers ? json_decode( (string) $row->answers, true ) : null;

		$summary['review'] = is_array( $answers ) ? $answers : null;

		return $summary;
	}

	/**
	 * User meta key for a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return string
	 */
	public static function meta_key( int $lesson_id ): string {
		return Options::META_PREFIX . 'quiz_' . $lesson_id;
	}
}
