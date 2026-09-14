<?php
/**
 * Per-question aggregation over stored attempts.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Turns answer snapshots into "which question does everyone get wrong".
 *
 * That signal is usually about the lesson or the wording, not the learner.
 * Pure: no WordPress calls, no database access.
 */
final class QuizQuestionStats {

	/**
	 * Aggregate snapshots into one row per question, in first-seen order.
	 *
	 * Open questions are only counted as answered or skipped, since they are
	 * never scored.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $snapshots Answer snapshots.
	 * @return array<int, array<string, mixed>>
	 */
	public static function aggregate( array $snapshots ): array {
		$stats = [];

		foreach ( $snapshots as $snapshot ) {
			foreach ( $snapshot as $entry ) {
				$id = (string) ( $entry['id'] ?? '' );

				if ( '' === $id ) {
					continue;
				}

				$stats[ $id ] = self::count( $stats[ $id ] ?? self::blank( $id, $entry ), $entry );
			}
		}

		return array_values( array_map( [ self::class, 'with_ratio' ], $stats ) );
	}

	/**
	 * Empty counters for a question.
	 *
	 * @param string               $id    Question id.
	 * @param array<string, mixed> $entry Snapshot entry.
	 * @return array<string, mixed>
	 */
	private static function blank( string $id, array $entry ): array {
		return [
			'id'       => $id,
			'prompt'   => (string) ( $entry['prompt'] ?? '' ),
			'type'     => (string) ( $entry['type'] ?? '' ),
			'answered' => 0,
			'correct'  => 0,
			'wrong'    => 0,
			'skipped'  => 0,
		];
	}

	/**
	 * Add one snapshot entry to a question's counters.
	 *
	 * @param array<string, mixed> $row   Counters so far.
	 * @param array<string, mixed> $entry Snapshot entry.
	 * @return array<string, mixed>
	 */
	private static function count( array $row, array $entry ): array {
		// A later attempt may carry a reworded prompt; show the newest.
		if ( ! empty( $entry['prompt'] ) ) {
			$row['prompt'] = (string) $entry['prompt'];
		}

		if ( false === ( $entry['scored'] ?? true ) ) {
			$given = isset( $entry['given_text'] ) && '' !== $entry['given_text'];
			$key   = $given ? 'answered' : 'skipped';

			$row[ $key ] = (int) $row[ $key ] + 1;

			return $row;
		}

		$row['answered'] = (int) $row['answered'] + 1;
		$key             = ( $entry['correct'] ?? false ) ? 'correct' : 'wrong';
		$row[ $key ]     = (int) $row[ $key ] + 1;

		return $row;
	}

	/**
	 * Add the share of correct answers among the scored ones.
	 *
	 * @param array<string, mixed> $row Counters.
	 * @return array<string, mixed>
	 */
	private static function with_ratio( array $row ): array {
		$correct = (int) $row['correct'];
		$scored  = $correct + (int) $row['wrong'];

		$row['correct_ratio'] = $scored > 0 ? round( $correct / $scored * 100, 1 ) : null;

		return $row;
	}
}
