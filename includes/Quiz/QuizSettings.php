<?php
/**
 * Quiz settings resolution.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Options;

/**
 * Resolves per-quiz values against the global settings.
 */
final class QuizSettings {

	/**
	 * Pass threshold: the quiz's own value, else the global default, clamped to 0-100.
	 *
	 * @param array<string, mixed> $quiz Normalized quiz.
	 * @return float
	 */
	public static function pass_percentage( array $quiz ): float {
		$value = $quiz['pass_percentage'] ?? Options::get( 'quiz_pass_percentage', 80 );

		return max( 0.0, min( 100.0, (float) $value ) );
	}

	/**
	 * Whether passing the quiz is required to complete a lesson.
	 *
	 * @return bool
	 */
	public static function require_pass(): bool {
		return (bool) Options::get( 'require_quiz_pass', false );
	}
}
