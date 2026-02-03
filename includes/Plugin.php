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
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
		$this->init_components();
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
}
