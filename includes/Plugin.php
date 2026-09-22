<?php
/**
 * Main Plugin class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

use LightweightPlugins\LMS\Admin\LessonListColumns;
use LightweightPlugins\LMS\Admin\SettingsPage;
use LightweightPlugins\LMS\Admin\Assets;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseContentMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseAccessMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseDataMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonCourseMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonVideoMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonDataMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonQuizMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\CourseDripMetabox;
use LightweightPlugins\LMS\Admin\Metaboxes\LessonDripMetabox;
use LightweightPlugins\LMS\Admin\QuizResultsPage;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Taxonomies\CourseCategory;
use LightweightPlugins\LMS\Taxonomies\CourseTag;
use LightweightPlugins\LMS\Taxonomies\CourseLevel;
use LightweightPlugins\LMS\Meta\CourseMeta;
use LightweightPlugins\LMS\Meta\LessonMeta;
use LightweightPlugins\LMS\Meta\DripMeta;
use LightweightPlugins\LMS\Drip\CourseStart;
use LightweightPlugins\LMS\Drip\LessonLocks;
use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\CLI\MigrateLearnDashCommand;
use LightweightPlugins\LMS\CLI\CourseCreateCommand;
use LightweightPlugins\LMS\CLI\CourseListCommand;
use LightweightPlugins\LMS\CLI\CourseDeleteCommand;
use LightweightPlugins\LMS\CLI\CourseSetSectionCommand;
use LightweightPlugins\LMS\CLI\LessonCreateCommand;
use LightweightPlugins\LMS\CLI\LessonListCommand;
use LightweightPlugins\LMS\CLI\LessonAssignCommand;
use LightweightPlugins\LMS\CLI\LessonSetQuizCommand;
use LightweightPlugins\LMS\CLI\LessonGetQuizCommand;
use LightweightPlugins\LMS\CLI\LessonDeleteQuizCommand;
use LightweightPlugins\LMS\CLI\EnrollCommand;
use LightweightPlugins\LMS\CLI\RevokeCommand;
use LightweightPlugins\LMS\CLI\ForceCompleteCommand;
use LightweightPlugins\LMS\CLI\CourseSetDripCommand;
use LightweightPlugins\LMS\CLI\LessonSetDripCommand;
use LightweightPlugins\LMS\CLI\DripStatusCommand;
use LightweightPlugins\LMS\CLI\DripSetStartCommand;
use LightweightPlugins\LMS\Admin\UserProfile;
use LightweightPlugins\LMS\Access\AccessGranter;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;
use LightweightPlugins\LMS\SiteManager\Integration as SiteManagerIntegration;
use LightweightPlugins\LMS\LwCookie\Integration as LwCookieIntegration;

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

		// A learner's drip clock starts at their first grant, whatever the
		// source, even if the course only starts dripping later.
		add_action( 'lw_lms_after_grant', [ CourseStart::class, 'on_grant' ], 10, 2 );

		// Completing a lesson can open the next one in the same request.
		add_action( 'lw_lms_lesson_completed', [ LessonLocks::class, 'flush' ] );
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
			new UserProfile();
			new CourseContentMetabox();
			new CourseAccessMetabox();
			new CourseDataMetabox();
			new LessonCourseMetabox();
			new LessonVideoMetabox();
			new LessonDataMetabox();
			new LessonQuizMetabox();
			new CourseDripMetabox();
			new LessonDripMetabox();
			new QuizResultsPage();
			LessonListColumns::register();
		}

		// REST API.
		$rest_api = new RestApi();
		$rest_api->init();

		// Access granter (WC order hook).
		new AccessGranter();

		// WooCommerce integration (self-checks if WooCommerce is active).
		new WooCommerce();

		// LW Site Manager integration (safe to call even if not active).
		SiteManagerIntegration::init();

		// LW Cookie integration: consent placeholder for lesson videos (no-op without LW Cookie).
		LwCookieIntegration::init();
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
		DripMeta::register();
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

		\WP_CLI::add_command( 'lw-lms course create', CourseCreateCommand::class );
		\WP_CLI::add_command( 'lw-lms course list', CourseListCommand::class );
		\WP_CLI::add_command( 'lw-lms course delete', CourseDeleteCommand::class );
		\WP_CLI::add_command( 'lw-lms course set-section', CourseSetSectionCommand::class );
		\WP_CLI::add_command( 'lw-lms course set-drip', CourseSetDripCommand::class );

		\WP_CLI::add_command( 'lw-lms lesson create', LessonCreateCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson list', LessonListCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson assign', LessonAssignCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson set-quiz', LessonSetQuizCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson get-quiz', LessonGetQuizCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson delete-quiz', LessonDeleteQuizCommand::class );
		\WP_CLI::add_command( 'lw-lms lesson set-drip', LessonSetDripCommand::class );

		\WP_CLI::add_command( 'lw-lms enroll', EnrollCommand::class );
		\WP_CLI::add_command( 'lw-lms revoke', RevokeCommand::class );
		\WP_CLI::add_command( 'lw-lms force-complete', ForceCompleteCommand::class );

		\WP_CLI::add_command( 'lw-lms drip status', DripStatusCommand::class );
		\WP_CLI::add_command( 'lw-lms drip set-start', DripSetStartCommand::class );
	}
}
