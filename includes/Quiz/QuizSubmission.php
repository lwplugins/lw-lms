<?php
/**
 * Quiz submission workflow.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Progress\ProgressCalculator;
use LightweightPlugins\LMS\Progress\ProgressRepository;

/**
 * Scores a submission, records the attempt, completes the lesson when the
 * quiz is graded, and fires the quiz hooks.
 */
final class QuizSubmission {

	/**
	 * Submit answers for a lesson's quiz.
	 *
	 * @param int                  $lesson_id Lesson ID.
	 * @param int                  $user_id   User ID.
	 * @param array<string, mixed> $quiz      Normalized quiz.
	 * @param array<string, mixed> $answers   Answers keyed by question id.
	 * @return array<string, mixed> Scoring result (see QuizScorer::score()).
	 */
	public static function submit( int $lesson_id, int $user_id, array $quiz, array $answers ): array {
		$result    = QuizScorer::score( $quiz, $answers, QuizSettings::pass_percentage( $quiz ) );
		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		QuizAttempts::record( $user_id, $lesson_id, $course_id, $result, QuizSnapshot::build( $quiz, $answers, $result['results'] ) );

		if ( $result['passed'] ) {
			self::maybe_complete_lesson( $lesson_id, $user_id, $course_id );
		}

		/**
		 * Fires after every quiz submission has been scored and recorded.
		 *
		 * @since 1.8.0
		 *
		 * @param int   $lesson_id  Lesson ID.
		 * @param int   $user_id    User ID.
		 * @param float $percentage Score percentage (0-100).
		 * @param bool  $passed     Whether the pass threshold was met.
		 */
		do_action( 'lw_lms_quiz_submitted', $lesson_id, $user_id, $result['percentage'], $result['passed'] );

		if ( $result['passed'] ) {
			/**
			 * Fires when a quiz submission meets the pass threshold (on every
			 * passing attempt, not only the first).
			 *
			 * @since 1.8.0
			 *
			 * @param int   $lesson_id  Lesson ID.
			 * @param int   $user_id    User ID.
			 * @param float $percentage Score percentage (0-100).
			 */
			do_action( 'lw_lms_quiz_passed', $lesson_id, $user_id, $result['percentage'] );
		}

		return $result;
	}

	/**
	 * With require_quiz_pass on, a pass completes the lesson through the
	 * regular progress path (so lesson/course completion hooks fire).
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID (0 when unassigned).
	 * @return void
	 */
	private static function maybe_complete_lesson( int $lesson_id, int $user_id, int $course_id ): void {
		if ( ! QuizSettings::require_pass() ) {
			return;
		}

		if ( ! $course_id || ProgressCalculator::is_lesson_completed( $user_id, $lesson_id ) ) {
			return;
		}

		ProgressRepository::upsert( $user_id, $course_id, $lesson_id, 'completed' );
	}
}
