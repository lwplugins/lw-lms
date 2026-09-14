<?php
/**
 * Quiz attempt table creation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Creates and manages the quiz attempt table.
 *
 * `course_id` is denormalized on purpose: without it every report query would
 * have to join postmeta through `_lw_lms_lesson_course_id`.
 */
final class QuizAttemptTable {

	/**
	 * Table name without prefix.
	 */
	public const TABLE_NAME = 'lms_quiz_attempts';

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the attempt table.
	 *
	 * @return void
	 */
	public static function create(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			lesson_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
			score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			scored_questions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			passed TINYINT(1) NOT NULL DEFAULT 0,
			answers LONGTEXT DEFAULT NULL,
			submitted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_user_lesson (user_id, lesson_id),
			KEY idx_lesson_submitted (lesson_id, submitted_at),
			KEY idx_course (course_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the attempt table.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
