<?php
/**
 * Server-side quiz scoring.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Scores submitted answers against a normalized quiz. Pure: no WordPress calls.
 */
final class QuizScorer {

	/**
	 * Score answers.
	 *
	 * Missing or malformed answers count as wrong. Open questions are not
	 * scored; a quiz with no scored questions passes at 100%. The correct
	 * answer is revealed only for wrongly answered questions.
	 *
	 * @param array<string, mixed> $quiz            Normalized quiz.
	 * @param array<string, mixed> $answers         Answers keyed by question id.
	 * @param float                $pass_percentage Pass threshold (0-100).
	 * @return array{score: int, scored_questions: int, percentage: float, passed: bool, pass_percentage: float, results: array<string, array<string, mixed>>}
	 */
	public static function score( array $quiz, array $answers, float $pass_percentage ): array {
		$score   = 0;
		$scored  = 0;
		$results = [];

		foreach ( $quiz['questions'] as $question ) {
			$id     = $question['id'];
			$answer = $answers[ $id ] ?? null;

			if ( 'open' === $question['type'] ) {
				$results[ $id ] = [ 'scored' => false ];
				if ( isset( $question['sample'] ) ) {
					$results[ $id ]['sample'] = $question['sample'];
				}
				continue;
			}

			$result = 'single' === $question['type']
				? self::check_single( $question['options'], $answer )
				: self::check_boolean( $question['correct'], $answer );

			++$scored;
			$score         += $result['correct'] ? 1 : 0;
			$results[ $id ] = $result;
		}

		// `passed` is compared against the same rounded value that is returned.
		$percentage = $scored > 0 ? round( $score / $scored * 100, 2 ) : 100.0;

		return [
			'score'            => $score,
			'scored_questions' => $scored,
			'percentage'       => $percentage,
			'passed'           => $percentage >= $pass_percentage,
			'pass_percentage'  => $pass_percentage,
			'results'          => $results,
		];
	}

	/**
	 * Check a single-choice answer (option id, or a 0-based index into the
	 * stored order for clients written against 1.8.0).
	 *
	 * @param array<int, array<string, mixed>> $options Options.
	 * @param mixed                            $answer  Submitted answer.
	 * @return array<string, mixed>
	 */
	private static function check_single( array $options, mixed $answer ): array {
		$correct_index = 0;
		foreach ( $options as $index => $option ) {
			if ( true === ( $option['correct'] ?? false ) ) {
				$correct_index = $index;
				break;
			}
		}

		if ( QuizOptions::resolve( $options, $answer ) === $correct_index ) {
			return [ 'correct' => true ];
		}

		return [
			'correct'           => false,
			// Index kept for 1.8.0 clients; the id is what a client that
			// received shuffled options can actually use.
			'correct_option'    => $correct_index,
			'correct_option_id' => QuizOptions::ids( $options )[ $correct_index ],
		];
	}

	/**
	 * Check a true/false answer (strict boolean).
	 *
	 * @param bool  $expected Correct answer.
	 * @param mixed $answer   Submitted answer.
	 * @return array<string, mixed>
	 */
	private static function check_boolean( bool $expected, mixed $answer ): array {
		if ( $answer === $expected ) {
			return [ 'correct' => true ];
		}

		return [
			'correct'        => false,
			'correct_answer' => $expected,
		];
	}
}
