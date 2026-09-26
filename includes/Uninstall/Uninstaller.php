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
 * summaries and drip start dates (on a network only when every site opted
 * in), the plugin options and the LMS capabilities. Course and lesson
 * posts themselves are kept.
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
	 * The learners' quiz summaries and drip start dates live in the user
	 * meta table, which every site of a network shares. On a network they
	 * are deleted only when every site opted in, so a site that keeps its
	 * data also keeps its learners' user meta.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::run_site();
			return;
		}

		$site_ids = array_map(
			'intval',
			get_sites(
				[
					'fields' => 'ids',
					'number' => 0,
				]
			)
		);
		$every    = true;

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$every = self::run_site( false ) && $every;
			restore_current_blog();
		}

		if ( $every && [] !== $site_ids ) {
			self::delete_user_meta();
		}
	}

	/**
	 * Clean up the current site when its owner opted in.
	 *
	 * @param bool $with_user_meta Also delete the LMS user meta (shared by
	 *                             every site of a network).
	 * @return bool Whether data was deleted.
	 */
	public static function run_site( bool $with_user_meta = true ): bool {
		if ( ! self::opted_in( get_option( Options::OPTION_NAME ) ) ) {
			return false;
		}

		self::delete_data();

		if ( $with_user_meta ) {
			self::delete_user_meta();
		}

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

	/**
	 * Delete the learners' quiz summaries (_lw_lms_quiz_{lesson}) and drip
	 * start dates (_lw_lms_course_start_{course}).
	 *
	 * @return void
	 */
	private static function delete_user_meta(): void {
		global $wpdb;

		foreach ( [ 'quiz_', 'course_start_' ] as $suffix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$wpdb->esc_like( Options::META_PREFIX . $suffix ) . '%'
				)
			);
		}
	}
}
