<?php
/**
 * Self-contained record of one submission.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Builds the answer snapshot stored with an attempt.
 *
 * Carries prompts and option texts, not only ids: a later `set-quiz` may
 * reword or reorder the quiz, and the stored attempt must stay readable
 * (and defensible) on its own. Pure: no WordPress calls.
 */
final class QuizSnapshot {

	/**
	 * Build the snapshot for a scored submission.
	 *
	 * @param array<string, mixed>                $quiz    Normalized quiz.
	 * @param array<string, mixed>                $answers Submitted answers by question id.
	 * @param array<string, array<string, mixed>> $results Scoring results by question id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build( array $quiz, array $answers, array $results ): array {
		$snapshot = [];

		foreach ( $quiz['questions'] as $question ) {
			$id     = $question['id'];
			$answer = $answers[ $id ] ?? null;
			$result = $results[ $id ] ?? [];

			$entry = [
				'id'     => $id,
				'type'   => $question['type'],
				'prompt' => $question['prompt'],
			];

			$snapshot[] = 'open' === $question['type']
				? self::open_entry( $entry, $answer )
				: self::scored_entry( $entry, $question, $answer, $result );
		}

		return $snapshot;
	}

	/**
	 * Open questions keep the text only; they are never scored.
	 *
	 * @param array<string, mixed> $entry  Entry so far.
	 * @param mixed                $answer Submitted answer.
	 * @return array<string, mixed>
	 */
	private static function open_entry( array $entry, mixed $answer ): array {
		if ( is_string( $answer ) && '' !== trim( $answer ) ) {
			$entry['given_text'] = trim( $answer );
		}

		$entry['scored'] = false;

		return $entry;
	}

	/**
	 * Single-choice and true/false questions record what was given and, when
	 * wrong, what would have been right.
	 *
	 * @param array<string, mixed> $entry    Entry so far.
	 * @param array<string, mixed> $question Normalized question.
	 * @param mixed                $answer   Submitted answer.
	 * @param array<string, mixed> $result   Scoring result.
	 * @return array<string, mixed>
	 */
	private static function scored_entry( array $entry, array $question, mixed $answer, array $result ): array {
		$correct = (bool) ( $result['correct'] ?? false );

		if ( 'single' === $question['type'] ) {
			$options = $question['options'];
			$ids     = QuizOptions::ids( $options );
			$given   = QuizOptions::resolve( $options, $answer );

			$entry['given'] = null !== $given ? $ids[ $given ] : null;

			if ( null !== $given ) {
				$entry['given_text'] = $options[ $given ]['text'];
			}

			$entry['correct'] = $correct;

			if ( ! $correct && isset( $result['correct_option'] ) ) {
				$entry['correct_text'] = $options[ $result['correct_option'] ]['text'];
			}

			return $entry;
		}

		$entry['given']   = is_bool( $answer ) ? $answer : null;
		$entry['correct'] = $correct;

		if ( ! $correct ) {
			$entry['correct_answer'] = $question['correct'];
		}

		return $entry;
	}
}
