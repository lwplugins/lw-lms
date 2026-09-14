<?php
/**
 * Quiz attempt writes.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Write operations for the attempt table. Reads live in QuizAttemptQueries.
 */
final class QuizAttemptRepository {

	/**
	 * Store one attempt.
	 *
	 * @param int                              $user_id      User ID.
	 * @param int                              $lesson_id    Lesson ID.
	 * @param int                              $course_id    Course ID (0 when unassigned).
	 * @param array<string, mixed>             $result       Scoring result (see QuizScorer::score()).
	 * @param array<int, array<string, mixed>> $snapshot     Answer snapshot.
	 * @param string                           $submitted_at MySQL datetime.
	 * @return int Inserted row id, or 0 on failure.
	 */
	public static function record(
		int $user_id,
		int $lesson_id,
		int $course_id,
		array $result,
		array $snapshot,
		string $submitted_at
	): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			QuizAttemptTable::get_table_name(),
			[
				'user_id'          => $user_id,
				'lesson_id'        => $lesson_id,
				'course_id'        => $course_id,
				'percentage'       => $result['percentage'],
				'score'            => $result['score'],
				'scored_questions' => $result['scored_questions'],
				'passed'           => $result['passed'] ? 1 : 0,
				'answers'          => wp_json_encode( $snapshot ),
				'submitted_at'     => $submitted_at,
			],
			[ '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%s', '%s' ]
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Delete every attempt of a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return int Rows deleted.
	 */
	public static function delete_for_lesson( int $lesson_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( QuizAttemptTable::get_table_name(), [ 'lesson_id' => $lesson_id ], [ '%d' ] );

		return is_int( $deleted ) ? $deleted : 0;
	}
}
