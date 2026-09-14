<?php
/**
 * Quiz storage (one meta per lesson).
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;

/**
 * Reads and writes the `_lw_lms_quiz` lesson meta.
 *
 * Deliberately NOT registered with register_post_meta( show_in_rest ): the
 * lesson CPT is exposed at /wp/v2/lesson, which would leak correct answers.
 */
final class QuizRepository {

	/**
	 * Meta key (without prefix).
	 */
	private const META_KEY = 'quiz';

	/**
	 * Get a lesson's quiz, or null when it has none.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $lesson_id ): ?array {
		$quiz = Options::get_post_meta( $lesson_id, self::META_KEY, null );

		return is_array( $quiz ) && ! empty( $quiz['questions'] ) ? $quiz : null;
	}

	/**
	 * Replace a lesson's quiz with a normalized document.
	 *
	 * @param int                  $lesson_id Lesson ID.
	 * @param array<string, mixed> $quiz      Normalized quiz.
	 * @return bool True when the stored quiz equals the given one.
	 */
	public static function save( int $lesson_id, array $quiz ): bool {
		// update_post_meta() unslashes its value: slash first so backslashes in
		// prompts and options survive.
		Options::set_post_meta( $lesson_id, self::META_KEY, wp_slash( $quiz ) );

		// update_post_meta() returns false for an unchanged value (re-import),
		// so confirm by reading back instead.
		return self::get( $lesson_id ) === $quiz;
	}

	/**
	 * Delete a lesson's quiz.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function delete( int $lesson_id ): bool {
		return delete_post_meta( $lesson_id, Options::META_PREFIX . self::META_KEY );
	}
}
