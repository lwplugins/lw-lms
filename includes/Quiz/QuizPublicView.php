<?php
/**
 * Learner-facing quiz payload.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Builds the quiz object for GET /lessons/{id}: every `correct` key is
 * stripped, so answers are only ever revealed by the submission response.
 */
final class QuizPublicView {

	/**
	 * Quiz payload for a lesson, or null when it has none.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID (0 = guest).
	 * @return array<string, mixed>|null
	 */
	public static function for_lesson( int $lesson_id, int $user_id ): ?array {
		$quiz = QuizRepository::get( $lesson_id );

		if ( null === $quiz ) {
			return null;
		}

		$view                 = self::build( $quiz, QuizSettings::pass_percentage( $quiz ) );
		$view['last_attempt'] = $user_id ? QuizAttempts::get( $user_id, $lesson_id ) : null;

		return $view;
	}

	/**
	 * Strip answers from a normalized quiz.
	 *
	 * @param array<string, mixed> $quiz            Normalized quiz.
	 * @param float                $pass_percentage Resolved pass threshold.
	 * @return array<string, mixed>
	 */
	public static function build( array $quiz, float $pass_percentage ): array {
		return [
			'pass_percentage' => $pass_percentage,
			'shuffle_options' => (bool) ( $quiz['shuffle_options'] ?? false ),
			'questions'       => array_map(
				static fn ( array $question ): array => self::question( $question ),
				$quiz['questions']
			),
		];
	}

	/**
	 * Public fields of one question.
	 *
	 * @param array<string, mixed> $question Normalized question.
	 * @return array<string, mixed>
	 */
	private static function question( array $question ): array {
		$public = [
			'id'     => $question['id'],
			'type'   => $question['type'],
			'prompt' => $question['prompt'],
		];

		if ( 'single' === $question['type'] ) {
			$public['options'] = array_map(
				static fn ( array $option ): array => [ 'text' => $option['text'] ],
				$question['options']
			);
		}

		if ( 'open' === $question['type'] && isset( $question['sample'] ) ) {
			$public['sample'] = $question['sample'];
		}

		return $public;
	}
}
