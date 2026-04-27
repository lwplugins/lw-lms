<?php
/**
 * Course completion snapshot table.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Progress;

/**
 * Stores a row per user × course at the moment the user reaches 100%.
 *
 * Issue #7: locks the percentage to "100% of the lesson list as it was when
 * the user finished," so adding a lesson to the course later does not knock
 * a completed user back to 80% / 90% / etc.
 */
final class ProgressSnapshotTable {

	public const TABLE_NAME = 'lms_completion_snapshots';

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
	 * Create the snapshot table.
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
			course_id BIGINT UNSIGNED NOT NULL,
			total_lessons INT UNSIGNED NOT NULL DEFAULT 0,
			completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY idx_course (course_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the snapshot table.
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
