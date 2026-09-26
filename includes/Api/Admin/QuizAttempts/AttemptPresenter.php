<?php
/**
 * Quiz attempts for the admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\QuizAttempts;

use LightweightPlugins\LMS\Api\Admin\AdminFormat;

/**
 * Turns attempt rows into the shape the Quiz results screen reads.
 *
 * Answers: administrators (manage_options) see the full record, including
 * which answers were right and what the right answer was. Other LMS
 * managers see what the learner answered and the score, never whether a
 * single answer was right, since that reveals the answer key.
 */
final class AttemptPresenter {

	/**
	 * A page of rows.
	 *
	 * @param array<int, object> $rows Attempt rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( array $rows ): array {
		AdminFormat::prime(
			array_map( static fn ( object $row ): int => (int) $row->user_id, $rows ),
			array_merge(
				array_map( static fn ( object $row ): int => (int) $row->lesson_id, $rows ),
				array_map( static fn ( object $row ): int => (int) $row->course_id, $rows )
			)
		);

		return array_map( [ self::class, 'row' ], $rows );
	}

	/**
	 * One list row.
	 *
	 * @param object $row Attempt row.
	 * @return array<string, mixed>
	 */
	public static function row( object $row ): array {
		return [
			'id'              => (int) $row->id,
			'userId'          => (int) $row->user_id,
			'user'            => AdminFormat::user( (int) $row->user_id ),
			'lesson'          => AdminFormat::post( (int) $row->lesson_id ),
			'lessonId'        => (int) $row->lesson_id,
			'course'          => AdminFormat::post( (int) $row->course_id ),
			'percentage'      => round( (float) $row->percentage, 1 ),
			'score'           => (int) $row->score,
			'scoredQuestions' => (int) $row->scored_questions,
			'passed'          => (bool) (int) $row->passed,
			'submittedAt'     => (string) $row->submitted_at,
			'submitted'       => AdminFormat::local_datetime( (string) $row->submitted_at ),
		];
	}

	/**
	 * One attempt with its answers.
	 *
	 * @param object $row    Attempt row (with the answers column).
	 * @param bool   $reveal Whether the viewer may see the answer key.
	 * @return array<string, mixed>
	 */
	public static function detail( object $row, bool $reveal ): array {
		$decoded = json_decode( (string) ( $row->answers ?? '' ), true );

		return array_merge(
			self::row( $row ),
			[
				'answers'      => self::answers( is_array( $decoded ) ? $decoded : [], $reveal ),
				'showsAnswers' => $reveal,
			]
		);
	}

	/**
	 * The answer snapshot, answer key removed unless the viewer may see it.
	 *
	 * @param array<int|string, mixed> $snapshot Stored snapshot.
	 * @param bool                     $reveal   Keep the answer key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function answers( array $snapshot, bool $reveal ): array {
		$out = [];

		foreach ( $snapshot as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$answer = [
				'id'        => (string) ( $entry['id'] ?? '' ),
				'type'      => (string) ( $entry['type'] ?? '' ),
				'prompt'    => (string) ( $entry['prompt'] ?? '' ),
				'scored'    => false !== ( $entry['scored'] ?? true ),
				'given'     => $entry['given'] ?? null,
				'givenText' => isset( $entry['given_text'] ) ? (string) $entry['given_text'] : null,
			];

			if ( $reveal ) {
				$answer['correct']       = isset( $entry['correct'] ) ? (bool) $entry['correct'] : null;
				$answer['correctText']   = isset( $entry['correct_text'] ) ? (string) $entry['correct_text'] : null;
				$answer['correctAnswer'] = isset( $entry['correct_answer'] ) ? (bool) $entry['correct_answer'] : null;
			}

			$out[] = $answer;
		}

		return $out;
	}
}
