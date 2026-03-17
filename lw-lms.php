<?php
/**
 * Plugin Name:       LW LMS
 * Plugin URI:        https://github.com/lwplugins/lw-lms
 * Description:       Lightweight LMS — courses, lessons, and progress tracking.
 * Version:           1.2.10
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            LW Plugins
 * Author URI:        https://lwplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lw-lms
 * Domain Path:       /languages
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LW_LMS_VERSION', '1.2.10' );
define( 'LW_LMS_FILE', __FILE__ );
define( 'LW_LMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'LW_LMS_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (required for PSR-4 class loading).
if ( file_exists( LW_LMS_PATH . 'vendor/autoload.php' ) ) {
	require_once LW_LMS_PATH . 'vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>LW LMS:</strong> %s</p></div>',
				esc_html__( 'Autoloader not found. Please run "composer install" in the plugin directory, or re-install the plugin from a release ZIP.', 'lw-lms' )
			);
		}
	);
	return;
}

/**
 * Returns the main plugin instance.
 *
 * @return Plugin
 */
function lw_lms(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function lw_lms_activate(): void {
	Activator::activate();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function lw_lms_deactivate(): void {
	Activator::deactivate();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\lw_lms_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\lw_lms_deactivate' );

// Initialize the plugin.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\lw_lms' );
