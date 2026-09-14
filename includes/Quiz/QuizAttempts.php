<?php
/**
 * Last quiz attempt per user and lesson.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;

/**
 * Stores the last attempt in user meta `_lw_lms_quiz_{lesson_id}` (no table,
 * no history). `passed_at` survives a later failed retry, so a learner who
 * already passed is not locked out again by the completion gate.
 */
final class QuizAttempts {

	/**
	 * Record an attempt.
	 *
	 * @param int   $user_id    User ID.
	 * @param int   $lesson_id  Lesson ID.
	 * @param float $percentage Score percentage.
	 * @param bool  $passed     Whether the threshold was met.
	 * @return array{percentage: float, passed: bool, submitted_at: string, passed_at: string|null}
	 */
	public static function record( int $user_id, int $lesson_id, float $percentage, bool $passed ): array {
		$now      = current_time( 'mysql' );
		$previous = self::get( $user_id, $lesson_id );

		$attempt = [
			'percentage'   => $percentage,
			'passed'       => $passed,
			'submitted_at' => $now,
			'passed_at'    => $passed ? $now : ( $previous['passed_at'] ?? null ),
		];

		update_user_meta( $user_id, self::meta_key( $lesson_id ), $attempt );

		return $attempt;
	}

	/**
	 * Get the last attempt, or null when there is none.
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
	 * User meta key for a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return string
	 */
	private static function meta_key( int $lesson_id ): string {
		return Options::META_PREFIX . 'quiz_' . $lesson_id;
	}
}
