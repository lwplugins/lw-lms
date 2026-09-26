<?php
/**
 * Uninstall script for LW LMS.
 *
 * Deletes data only on sites where "Delete all data when the plugin is
 * deleted" is on (LW Plugins → LMS → Advanced). By default every enrollment,
 * progress row, quiz attempt and setting is kept, so deleting the plugin to
 * reinstall it loses nothing. See LightweightPlugins\LMS\Uninstall\Uninstaller.
 *
 * @package LightweightPlugins\LMS
 */

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The plugin file is not loaded during uninstall, so load the autoloader:
// the local vendor (standalone/ZIP) or an already loaded root Composer one.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( class_exists( LightweightPlugins\LMS\Uninstall\Uninstaller::class ) ) {
	LightweightPlugins\LMS\Uninstall\Uninstaller::run();
} else {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No autoloader: the only way to say why the data was kept.
	error_log( 'lw-lms uninstall: autoloader not found, all LMS data was kept.' );
}
