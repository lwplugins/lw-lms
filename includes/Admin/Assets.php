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

		// Load on LW Plugins pages.
		$lw_pages = [
			'toplevel_page_' . ParentPage::SLUG,
			ParentPage::SLUG . '_page_' . SettingsPage::SLUG,
		];

		// Load on course and lesson edit pages.
		$is_course_page = $screen && Course::POST_TYPE === $screen->post_type;
		$is_lesson_page = $screen && Lesson::POST_TYPE === $screen->post_type;
		$is_lw_page     = in_array( $hook, $lw_pages, true );

		if ( ! $is_lw_page && ! $is_course_page && ! $is_lesson_page ) {
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lw_lms_admin' ),
				'i18n'    => [
					'confirmDelete' => __( 'Are you sure you want to remove this item?', 'lw-lms' ),
					'newSection'    => __( 'New Section', 'lw-lms' ),
					'untitled'      => __( 'Untitled', 'lw-lms' ),
				],
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
}
