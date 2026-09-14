<?php
/**
 * Completion gate for graded quizzes.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * With `require_quiz_pass` on, a lesson that has a quiz cannot be completed
 * through the learner-facing progress endpoint until its quiz is passed.
 */
final class QuizGate {

	/**
	 * Whether marking this lesson completed must be refused.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function blocks_completion( int $user_id, int $lesson_id ): bool {
		if ( ! QuizSettings::require_pass() || null === QuizRepository::get( $lesson_id ) ) {
			return false;
		}

		return ! QuizAttempts::has_passed( $user_id, $lesson_id );
	}
}
