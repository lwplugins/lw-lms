<?php
/**
 * Access Table creation.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Creates and manages the access database table.
 */
final class AccessTable {

	/**
	 * Table name without prefix.
	 */
	public const TABLE_NAME = 'lms_access';

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
	 * Create the access table.
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
			source VARCHAR(20) NOT NULL DEFAULT 'manual',
			source_id BIGINT UNSIGNED DEFAULT NULL,
			granted_at DATETIME NOT NULL,
			expires_at DATETIME DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			UNIQUE KEY user_course_source (user_id, course_id, source_id),
			KEY idx_user_course (user_id, course_id),
			KEY idx_status_expires (status, expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the access table.
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
