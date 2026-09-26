<?php
/**
 * Admin Assets class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Handles admin asset loading.
 */
final class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();

		// The LW Plugins overview (plugin cards). The LMS screen itself is
		// the React app and brings its own styles (SettingsPage).
		$lw_pages = [
			'toplevel_page_' . ParentPage::SLUG,
		];

		// Load on course and lesson edit pages.
		$is_course_page  = $screen && Course::POST_TYPE === $screen->post_type;
		$is_lesson_page  = $screen && Lesson::POST_TYPE === $screen->post_type;
		$is_lw_page      = in_array( $hook, $lw_pages, true );
		$is_profile_page = in_array( $hook, [ 'profile.php', 'user-edit.php' ], true );

		if ( ! $is_lw_page && ! $is_course_page && ! $is_lesson_page && ! $is_profile_page ) {
			return;
		}

		// Admin CSS.
		wp_enqueue_style(
			'lw-lms-admin',
			LW_LMS_URL . 'assets/css/admin.css',
			[],
			LW_LMS_VERSION
		);

		// Admin JS.
		wp_enqueue_script(
			'lw-lms-admin',
			LW_LMS_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			LW_LMS_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'lw-lms-admin',
			'lwLmsAdmin',
			[
				'i18n' => self::strings(),
			]
		);

		// Course builder JS (only on course edit page).
		if ( $is_course_page ) {
			wp_enqueue_script(
				'lw-lms-course-builder',
				LW_LMS_URL . 'assets/js/course-builder.js',
				[ 'jquery', 'jquery-ui-sortable', 'lw-lms-admin' ],
				LW_LMS_VERSION,
				true
			);
		}
	}

	/**
	 * Strings the admin scripts render.
	 *
	 * @return array<string, mixed>
	 */
	private static function strings(): array {
		return [
			'confirmDelete'          => __( 'Are you sure you want to remove this item?', 'lw-lms' ),
			'newSection'             => __( 'New Section', 'lw-lms' ),
			'untitled'               => __( 'Untitled', 'lw-lms' ),
			'save'                   => __( 'Save', 'lw-lms' ),
			'cancel'                 => __( 'Cancel', 'lw-lms' ),
			'sectionTitle'           => __( 'Section title', 'lw-lms' ),
			'dripAfter'              => __( 'after', 'lw-lms' ),
			'dripModes'              => [
				'none'       => __( 'Opens right away', 'lw-lms' ),
				'enrollment' => __( 'Opens after enrollment', 'lw-lms' ),
				'previous'   => __( 'Opens after the previous section', 'lw-lms' ),
			],
			'dripUnits'              => [
				'hour'  => __( 'hours', 'lw-lms' ),
				'day'   => __( 'days', 'lw-lms' ),
				'week'  => __( 'weeks', 'lw-lms' ),
				'month' => __( 'months', 'lw-lms' ),
			],
			'dripUnitsOne'           => [
				'hour'  => __( 'hour', 'lw-lms' ),
				'day'   => __( 'day', 'lw-lms' ),
				'week'  => __( 'week', 'lw-lms' ),
				'month' => __( 'month', 'lw-lms' ),
			],
			/* translators: 1: number, 2: unit such as days. */
			'dripSummaryEnrollment'  => __( 'opens %1$d %2$s after enrollment', 'lw-lms' ),
			/* translators: 1: number, 2: unit such as days. */
			'dripSummaryPrevious'    => __( 'opens %1$d %2$s after the previous section', 'lw-lms' ),
			'dripSummaryPreviousNow' => __( 'opens with the previous section completed', 'lw-lms' ),
		];
	}
}
