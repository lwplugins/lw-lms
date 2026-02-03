<?php
/**
 * Activator class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

use LightweightPlugins\LMS\Progress\ProgressTable;

/**
 * Handles plugin activation and deactivation.
 */
final class Activator {

	/**
	 * DB version constant.
	 */
	public const DB_VERSION = '1.0.0';

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
		update_option( 'lw_lms_db_version', self::DB_VERSION );
	}

	/**
	 * Add capabilities to admin role.
	 *
	 * @return void
	 */
	private static function add_capabilities(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$capabilities = [
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

		foreach ( $capabilities as $cap ) {
			$admin->add_cap( $cap );
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
