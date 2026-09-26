<?php
/**
 * Quiz attempt search for the admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use LightweightPlugins\LMS\Privacy\EmailVisibility;

/**
 * Filtered, paginated reads of the attempt table across lessons.
 *
 * Filters: course, lesson, user (ints), search (user login or name; also
 * the email when the EmailVisibility::SEARCH_FLAG filter is set),
 * passed (bool), from / to (Y-m-d, site time, inclusive).
 */
final class QuizAttemptSearch {

	/**
	 * Columns of a list row (the answer snapshot is only read for one attempt).
	 */
	private const LIST_COLUMNS = 'q.id, q.user_id, q.lesson_id, q.course_id, q.percentage, q.score, q.scored_questions, q.passed, q.submitted_at';

	/**
	 * Rows of one page, newest first.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $limit   Rows to return.
	 * @param int                  $offset  Rows to skip.
	 * @return array<int, object>
	 */
	public static function rows( array $filters, int $limit, int $offset ): array {
		global $wpdb;

		[ $from, $where, $args ] = self::where( $filters, $wpdb->users, $wpdb->esc_like( (string) ( $filters['search'] ?? '' ) ) );

		$sql    = 'SELECT ' . self::LIST_COLUMNS . " FROM {$from} WHERE {$where} ORDER BY q.submitted_at DESC, q.id DESC LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Built from literals and placeholders in where().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Number of attempts matching the filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return int
	 */
	public static function count( array $filters ): int {
		global $wpdb;

		[ $from, $where, $args ] = self::where( $filters, $wpdb->users, $wpdb->esc_like( (string) ( $filters['search'] ?? '' ) ) );

		$sql = "SELECT COUNT(*) FROM {$from} WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Built from literals and placeholders in where().
		return (int) $wpdb->get_var( [] === $args ? $sql : $wpdb->prepare( $sql, ...$args ) );
	}

	/**
	 * One attempt with its answer snapshot.
	 *
	 * @param int $id Attempt ID.
	 * @return object|null
	 */
	public static function find( int $id ): ?object {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Attempts submitted since a moment.
	 *
	 * @param string $since Site-local MySQL datetime (same clock as submitted_at).
	 * @return int
	 */
	public static function count_since( string $since ): int {
		global $wpdb;
		$table = QuizAttemptTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(*) FROM {$table} WHERE submitted_at >= %s",
				$since
			)
		);
	}

	/**
	 * FROM and WHERE clauses with their placeholder values. Pure: every
	 * value goes through a placeholder, only literals are concatenated.
	 *
	 * @param array<string, mixed> $filters     Filters.
	 * @param string               $users_table Users table name.
	 * @param string               $search_like LIKE-escaped search text.
	 * @return array{0: string, 1: string, 2: array<int, int|string>}
	 */
	public static function where( array $filters, string $users_table, string $search_like ): array {
		$from    = QuizAttemptTable::get_table_name() . ' q';
		$clauses = [ '1=1' ];
		$args    = [];

		foreach ( [
			'course' => 'q.course_id',
			'lesson' => 'q.lesson_id',
			'user'   => 'q.user_id',
		] as $key => $column ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clauses[] = "{$column} = %d";
				$args[]    = (int) $filters[ $key ];
			}
		}

		if ( isset( $filters['passed'] ) && is_bool( $filters['passed'] ) ) {
			$clauses[] = 'q.passed = %d';
			$args[]    = $filters['passed'] ? 1 : 0;
		}

		if ( ! empty( $filters['from'] ) ) {
			$clauses[] = 'q.submitted_at >= %s';
			$args[]    = $filters['from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['to'] ) ) {
			$clauses[] = 'q.submitted_at <= %s';
			$args[]    = $filters['to'] . ' 23:59:59';
		}

		if ( '' !== $search_like ) {
			$from               .= " INNER JOIN {$users_table} u ON u.ID = q.user_id";
			[ $clause, $values ] = EmailVisibility::search_clause( $search_like, ! empty( $filters[ EmailVisibility::SEARCH_FLAG ] ) );
			$clauses[]           = $clause;
			array_push( $args, ...$values );
		}

		return [ $from, implode( ' AND ', $clauses ), $args ];
	}
}
