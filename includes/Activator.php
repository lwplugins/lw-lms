<?php
/**
 * Activator class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

use LightweightPlugins\LMS\Access\AccessTable;
use LightweightPlugins\LMS\Progress\ProgressSnapshotMigration;
use LightweightPlugins\LMS\Progress\ProgressSnapshotTable;
use LightweightPlugins\LMS\Progress\ProgressTable;
use LightweightPlugins\LMS\Quiz\QuizAttemptMigration;
use LightweightPlugins\LMS\Quiz\QuizAttemptTable;

/**
 * Handles plugin activation and deactivation.
 */
final class Activator {

	/**
	 * DB version constant.
	 */
	public const DB_VERSION = '1.3.0';

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::add_capabilities();
		self::set_default_options();

		// Flush rewrite rules after CPT registration.
		add_action( 'init', 'flush_rewrite_rules', 99 );
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		ProgressTable::create();
		AccessTable::create();
		ProgressSnapshotTable::create();
		QuizAttemptTable::create();

		// First time the snapshot table exists, retroactively freeze every
		// already-completed user × course pair so they don't drop below 100%
		// the moment someone adds a new lesson.
		ProgressSnapshotMigration::backfill();

		// 1.8.0 kept only a summary of the last quiz attempt in user meta;
		// lift those into the attempt table so history does not start empty.
		QuizAttemptMigration::backfill();

		update_option( 'lw_lms_db_version', self::DB_VERSION );
	}

	/**
	 * Add the LMS management capability to the administrator role.
	 *
	 * Courses and lessons use the regular post capabilities (capability_type
	 * "post"), so manage_lms is the only custom capability: it opens quiz
	 * results, enrollments, the progress abilities and the staff access.
	 * Earlier versions also added edit_courses, read_private_lessons, … which
	 * nothing checked; uninstall removes those leftovers.
	 *
	 * @return void
	 */
	private static function add_capabilities(): void {
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			$admin->add_cap( 'manage_lms' );
		}
	}

	/**
	 * Set default options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		if ( false === get_option( Options::OPTION_NAME ) ) {
			update_option( Options::OPTION_NAME, Options::get_defaults() );
		}
	}
}
