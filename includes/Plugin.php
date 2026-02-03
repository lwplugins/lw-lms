<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

use LightweightPlugins\LMS\Admin\SettingsPage;
use LightweightPlugins\LMS\Admin\Assets;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseContentMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseAccessMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseDataMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonCourseMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonVideoMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonDataMetabox;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Taxonomies\CourseCategory;
use LightweightPlugins\LMS\Taxonomies\CourseTag;
use LightweightPlugins\LMS\Taxonomies\CourseLevel;
use LightweightPlugins\LMS\Meta\CourseMeta;
use LightweightPlugins\LMS\Meta\LessonMeta;
use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\CLI\MigrateLearnDashCommand;
use LightweightPlugins\LMS\Access\AccessGranter;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->maybe_upgrade();
		$this->init_hooks();
		$this->init_components();
		$this->register_cli_commands();
	}

	/**
	 * Run DB upgrades if version changed.
	 *
	 * @return void
	 */
	private function maybe_upgrade(): void {
		$current = get_option( 'lw_lms_db_version', '0' );

		if ( version_compare( $current, Activator::DB_VERSION, '>=' ) ) {
			return;
		}

		Activator::activate();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		// Admin components.
		if ( is_admin() ) {
			new SettingsPage();
			new Assets();
			new CourseContentMetabox();
			new CourseAccessMetabox();
			new CourseDataMetabox();
			new LessonCourseMetabox();
			new LessonVideoMetabox();
			new LessonDataMetabox();
		}

		// REST API.
		$rest_api = new RestApi();
		$rest_api->init();

		// Access granter (WC order hook).
		new AccessGranter();

		// WooCommerce integration (self-checks if WooCommerce is active).
		new WooCommerce();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'lw-lms',
			false,
			dirname( plugin_basename( LW_LMS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		Course::register();
		Lesson::register();
	}

	/**
	 * Register taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		CourseCategory::register();
		CourseTag::register();
		CourseLevel::register();
	}

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		CourseMeta::register();
		LessonMeta::register();
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	private function register_cli_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'lw-lms migrate-learndash', MigrateLearnDashCommand::class );
	}
}
