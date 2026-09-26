<?php
/**
 * Settings Page class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\Api\Admin\AdminRoutes;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * The LMS screen under LW Plugins: a mount point for the React admin
 * (build/index), which reads and writes through the lw-lms/v1/admin routes.
 * Overview, Enrollments, Quiz results and the settings all live in it.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const SLUG = 'lw-lms';

	/**
	 * Slug of the former Quiz Results screen, kept as a redirect.
	 */
	public const LEGACY_RESULTS_SLUG = 'lw-lms-quiz-results';

	/**
	 * Script and style handle.
	 */
	private const HANDLE = 'lw-lms-admin-app';

	/**
	 * Documentation URL.
	 */
	private const DOCS_URL = 'https://github.com/lwplugins/lw-lms#readme';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * Assets are keyed on it rather than on a hard-coded
	 * "lw-plugins_page_lw-lms": WordPress derives that prefix from the
	 * translated parent menu title.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );
	}

	/**
	 * Add the menu page under LW Plugins, plus the hidden redirect of the
	 * former Quiz Results screen.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		ParentPage::maybe_register();

		// LMS managers and administrators both open the screen; the learner
		// sections inside need manage_lms.
		$capability = current_user_can( 'manage_lms' ) ? 'manage_lms' : 'manage_options';

		$hook = add_submenu_page(
			ParentPage::SLUG,
			__( 'LMS', 'lw-lms' ),
			__( 'LMS', 'lw-lms' ),
			$capability,
			self::SLUG,
			[ $this, 'render' ]
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';

		// Old bookmarks of admin.php?page=lw-lms-quiz-results land on the new section.
		$legacy = add_submenu_page( 'options.php', __( 'Quiz results', 'lw-lms' ), '', 'manage_lms', self::LEGACY_RESULTS_SLUG, '__return_null' );

		if ( is_string( $legacy ) ) {
			add_action( 'load-' . $legacy, [ self::class, 'redirect_legacy_results' ] );
		}
	}

	/**
	 * Redirect the former Quiz Results screen to its React section.
	 *
	 * @return void
	 */
	public static function redirect_legacy_results(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '#quiz-results' ) );
		exit;
	}

	/**
	 * Enqueue the React app on the screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}

		if ( ! BuildAssets::enqueue( 'index', self::HANDLE ) ) {
			return;
		}

		wp_add_inline_script( self::HANDLE, 'window.lwLmsAdmin = ' . wp_json_encode( self::boot_data() ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array<string, mixed>
	 */
	private static function boot_data(): array {
		return [
			'version'           => LW_LMS_VERSION,
			'namespace'         => AdminRoutes::NAMESPACE,
			'docsUrl'           => self::DOCS_URL,
			'canManageLearners' => AdminRoutes::can_manage_learners(),
			'woocommerce'       => WooCommerce::is_active(),
			'today'             => wp_date( 'Y-m-d' ),
			'links'             => [
				'courses'    => admin_url( 'edit.php?post_type=' . Course::POST_TYPE ),
				'newCourse'  => admin_url( 'post-new.php?post_type=' . Course::POST_TYPE ),
				'lessons'    => admin_url( 'edit.php?post_type=' . Lesson::POST_TYPE ),
				'newLesson'  => admin_url( 'post-new.php?post_type=' . Lesson::POST_TYPE ),
				'users'      => admin_url( 'users.php' ),
				'userEdit'   => admin_url( 'user-edit.php?user_id=' ),
				'wooOrders'  => admin_url( 'admin.php?page=wc-orders' ),
				'wooProduct' => admin_url( 'edit.php?post_type=product' ),
			],
		];
	}

	/**
	 * Mark the screen body for the app's styles.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( '' === $this->hook_suffix || ! $screen || $screen->id !== $this->hook_suffix ) {
			return $classes;
		}

		return $classes . ' lw-lms-screen';
	}

	/**
	 * Render the mount point (or a notice when the build is missing).
	 *
	 * The mount point sits outside .wrap so core's .wrap margins and
	 * NoticeManager's direct-child notice rules never reach the app; the
	 * missing-build notice carries `lw-notice` so it is not hidden.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! AdminRoutes::can_manage_settings() ) {
			return;
		}

		if ( ! BuildAssets::exists( 'index' ) ) {
			printf(
				'<div class="wrap"><h1>%s</h1><div class="notice notice-error lw-notice"><p>%s</p></div></div>',
				esc_html__( 'LW LMS', 'lw-lms' ),
				esc_html__( 'The admin screen files are missing. Re-install the plugin from a release ZIP, or run "npm install && npm run build" in the plugin directory.', 'lw-lms' )
			);
			return;
		}

		echo '<div id="lw-lms-root" class="lw-lms-root"></div>';
	}
}
