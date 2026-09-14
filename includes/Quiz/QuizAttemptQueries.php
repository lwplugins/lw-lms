<?php
/**
 * Quiz attempt reads.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Read queries for the attempt table. Writes live in QuizAttemptRepository.
 */
final class QuizAttemptQueries {

	/**
	 * How many attempts the per-question statistics look at.
	 */
	private const STATS_SAMPLE = 500;

	/**
	 * The most recent attempt of a user on a lesson.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return object|null
	 */
	public static function latest( int $user_id, int $lesson_id ): ?object {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE user_id = %d AND lesson_id = %d ORDER BY submitted_at DESC, id DESC LIMIT 1",
				$user_id,
				$lesson_id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Attempts of a lesson, newest first.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $limit     Rows to return.
	 * @param int $offset    Rows to skip.
	 * @return array<int, object>
	 */
	public static function for_lesson( int $lesson_id, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE lesson_id = %d ORDER BY submitted_at DESC, id DESC LIMIT %d OFFSET %d",
				$lesson_id,
				$limit,
				$offset
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Number of attempts on a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return int
	 */
	public static function count_for_lesson( int $lesson_id ): int {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(*) FROM {$table} WHERE lesson_id = %d",
				$lesson_id
			)
		);
	}

	/**
	 * One row per learner for a lesson: attempts, best and last result.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $limit     Rows to return.
	 * @param int $offset    Rows to skip.
	 * @return array<int, object>
	 */
	public static function learners( int $lesson_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		$select = 'SELECT user_id, COUNT(*) AS attempts, MAX(percentage) AS best_percentage,'
			. ' MAX(passed) AS ever_passed, MAX(submitted_at) AS last_submitted_at,'
			. " SUBSTRING_INDEX( GROUP_CONCAT( percentage ORDER BY submitted_at DESC, id DESC ), ',', 1 ) AS last_percentage";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		$sql = $select . " FROM {$table} WHERE lesson_id = %d GROUP BY user_id ORDER BY last_submitted_at DESC LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql is a literal with placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $lesson_id, $limit, $offset ) );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Number of learners who attempted a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return int
	 */
	public static function count_learners( int $lesson_id ): int {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE lesson_id = %d",
				$lesson_id
			)
		);
	}

	/**
	 * Per-question statistics over the most recent attempts of a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function question_stats( int $lesson_id ): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT answers FROM {$table} WHERE lesson_id = %d AND answers IS NOT NULL ORDER BY submitted_at DESC, id DESC LIMIT %d",
				$lesson_id,
				self::STATS_SAMPLE
			)
		);

		$snapshots = [];

		foreach ( (array) $rows as $json ) {
			$decoded = json_decode( (string) $json, true );

			if ( is_array( $decoded ) ) {
				$snapshots[] = $decoded;
			}
		}

		return QuizQuestionStats::aggregate( $snapshots );
	}

	/**
	 * Lesson ids that have at least one attempt.
	 *
	 * @return array<int, int>
	 */
	public static function lesson_ids(): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, no user input.
		$ids = $wpdb->get_col( "SELECT DISTINCT lesson_id FROM {$table}" );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Attempt count and best score of a user on a lesson.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return array{attempts: int, best_percentage: float}
	 */
	public static function totals( int $user_id, int $lesson_id ): array {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(*) AS attempts, MAX(percentage) AS best FROM {$table} WHERE user_id = %d AND lesson_id = %d",
				$user_id,
				$lesson_id
			)
		);

		return [
			'attempts'        => $row ? (int) $row->attempts : 0,
			'best_percentage' => $row ? (float) $row->best : 0.0,
		];
	}
}
