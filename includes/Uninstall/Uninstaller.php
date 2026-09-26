<?php
/**
 * Uninstall cleanup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Uninstall;

use LightweightPlugins\LMS\Access\AccessTable;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Progress\ProgressSnapshotTable;
use LightweightPlugins\LMS\Progress\ProgressTable;
use LightweightPlugins\LMS\Quiz\QuizAttemptTable;

/**
 * Removes the plugin's data when it is deleted, but only on sites where
 * "Delete all data when the plugin is deleted" (Advanced tab) is on.
 *
 * Off (the default) keeps everything, so deleting the plugin to reinstall it
 * loses no enrollment, progress or quiz history. On removes exactly what the
 * setting describes: the enrollment, progress, completion and quiz attempt
 * tables, the LMS settings stored on courses and lessons, the learners' quiz
 * summaries and drip start dates, the plugin options and the LMS
 * capabilities. Course and lesson posts themselves are kept.
 */
final class Uninstaller {

	/**
	 * Capabilities the plugin has ever added to roles.
	 */
	private const CAPABILITIES = [
		'manage_lms',
		'edit_courses',
		'edit_others_courses',
		'publish_courses',
		'read_private_courses',
		'delete_courses',
		'edit_lessons',
		'edit_others_lessons',
		'publish_lessons',
		'read_private_lessons',
		'delete_lessons',
	];

	/**
	 * Run for every site of a network, or for the single site.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::run_site();
			return;
		}

		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::run_site();
			restore_current_blog();
		}
	}

	/**
	 * Clean up the current site when its owner opted in.
	 *
	 * @return bool Whether data was deleted.
	 */
	public static function run_site(): bool {
		if ( ! self::opted_in( get_option( Options::OPTION_NAME ) ) ) {
			return false;
		}

		self::delete_data();

		return true;
	}

	/**
	 * Whether the stored options ask for data removal.
	 *
	 * @param mixed $options Stored lw_lms_options value.
	 * @return bool
	 */
	public static function opted_in( mixed $options ): bool {
		return is_array( $options ) && ! empty( $options['delete_data_on_uninstall'] );
	}

	/**
	 * Delete every piece of LMS data on the current site.
	 *
	 * @return void
	 */
	private static function delete_data(): void {
		global $wpdb;

		// LMS settings stored on courses and lessons (_lw_lms_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( Options::META_PREFIX ) . '%'
			)
		);

		// Quiz summaries (_lw_lms_quiz_{lesson}) and drip start dates
		// (_lw_lms_course_start_{course}). The user meta table is shared by
		// the whole network, so this also runs once per site (harmless).
		foreach ( [ 'quiz_', 'course_start_' ] as $suffix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$wpdb->esc_like( Options::META_PREFIX . $suffix ) . '%'
				)
			);
		}

		ProgressTable::drop();
		QuizAttemptTable::drop();
		ProgressSnapshotTable::drop();
		AccessTable::drop();

		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::CAPABILITIES as $capability ) {
				$role->remove_cap( $capability );
			}
		}

		delete_option( Options::OPTION_NAME );
		delete_option( 'lw_lms_db_version' );
	}
}
