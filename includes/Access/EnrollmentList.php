<?php
/**
 * Enrollment listing for the admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Filtered, paginated reads of the access table: one row per grant. An
 * "active" row whose expiry has passed is reported as expired.
 *
 * Filters: course (int), user (int), search (user login, email or name),
 * status (active|expired|revoked), source (string).
 */
final class EnrollmentList {

	/**
	 * Rows of one page, newest grant first.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $limit   Rows per page.
	 * @param int                  $offset  Rows to skip.
	 * @return array<int, object>
	 */
	public static function rows( array $filters, int $limit, int $offset ): array {
		global $wpdb;

		[ $from, $where, $args ] = self::where( $filters, current_time( 'mysql', true ), $wpdb->users, $wpdb->esc_like( (string) ( $filters['search'] ?? '' ) ) );

		$sql    = "SELECT a.* FROM {$from} WHERE {$where} ORDER BY a.granted_at DESC, a.id DESC LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Built from literals and placeholders in where().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Number of rows matching the filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return int
	 */
	public static function count( array $filters ): int {
		global $wpdb;

		[ $from, $where, $args ] = self::where( $filters, current_time( 'mysql', true ), $wpdb->users, $wpdb->esc_like( (string) ( $filters['search'] ?? '' ) ) );

		$sql = "SELECT COUNT(*) FROM {$from} WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Built from literals and placeholders in where().
		return (int) $wpdb->get_var( [] === $args ? $sql : $wpdb->prepare( $sql, ...$args ) );
	}

	/**
	 * The manual grant row of a learner in a course, if any.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function manual( int $user_id, int $course_id ): ?object {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d AND source = 'manual' AND source_id IS NULL ORDER BY id ASC LIMIT 1",
				$user_id,
				$course_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Learner-course pairs with access right now (several rows of one pair,
	 * e.g. an order and a manual grant, count once).
	 *
	 * @return int
	 */
	public static function count_active(): int {
		global $wpdb;

		$table = AccessTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(DISTINCT user_id, course_id) FROM {$table} WHERE status = 'active' AND (expires_at IS NULL OR expires_at > %s)",
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * FROM and WHERE clauses with their placeholder values. Pure: every
	 * value goes through a placeholder, only literals are concatenated.
	 *
	 * @param array<string, mixed> $filters     Filters.
	 * @param string               $now_utc     Current UTC MySQL datetime.
	 * @param string               $users_table Users table name.
	 * @param string               $search_like LIKE-escaped search text.
	 * @return array{0: string, 1: string, 2: array<int, int|string>}
	 */
	public static function where( array $filters, string $now_utc, string $users_table, string $search_like ): array {
		$from    = AccessTable::get_table_name() . ' a';
		$clauses = [ '1=1' ];
		$args    = [];

		foreach ( [
			'course' => 'a.course_id',
			'user'   => 'a.user_id',
		] as $key => $column ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clauses[] = "{$column} = %d";
				$args[]    = (int) $filters[ $key ];
			}
		}

		if ( ! empty( $filters['source'] ) ) {
			$clauses[] = 'a.source = %s';
			$args[]    = (string) $filters['source'];
		}

		$status = (string) ( $filters['status'] ?? '' );

		if ( 'active' === $status ) {
			$clauses[] = "a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at > %s)";
			$args[]    = $now_utc;
		} elseif ( 'expired' === $status ) {
			$clauses[] = "a.status = 'active' AND a.expires_at IS NOT NULL AND a.expires_at <= %s";
			$args[]    = $now_utc;
		} elseif ( 'revoked' === $status ) {
			$clauses[] = "a.status = 'revoked'";
		}

		if ( '' !== $search_like ) {
			$from     .= " INNER JOIN {$users_table} u ON u.ID = a.user_id";
			$clauses[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
			$like      = '%' . $search_like . '%';
			array_push( $args, $like, $like, $like );
		}

		return [ $from, implode( ' AND ', $clauses ), $args ];
	}

	/**
	 * Status of a row as the admin shows it.
	 *
	 * @param object $row     Access row.
	 * @param string $now_utc Current UTC MySQL datetime.
	 * @return string active|expired|revoked
	 */
	public static function status_of( object $row, string $now_utc ): string {
		if ( 'active' !== (string) $row->status ) {
			return 'revoked';
		}

		if ( ! empty( $row->expires_at ) && (string) $row->expires_at <= $now_utc ) {
			return 'expired';
		}

		return 'active';
	}
}
