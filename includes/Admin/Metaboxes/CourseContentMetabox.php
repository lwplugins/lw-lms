<?php
/**
 * Course Content Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Coordinates the Course Content metabox: registers it, delegates rendering
 * to CourseContentRenderer, and persists submitted data.
 */
final class CourseContentMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Course::POST_TYPE, [ $this, 'save' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_course_content',
			__( 'Course Content', 'lw-lms' ),
			[ $this, 'render' ],
			Course::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		( new CourseContentRenderer() )->render( $post );
	}

	/**
	 * Save the metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['lw_lms_course_content_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_course_content_nonce'] ), 'lw_lms_course_content' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['lw_lms_course_sections'] ) ) {
			$sections = json_decode( sanitize_text_field( wp_unslash( $_POST['lw_lms_course_sections'] ) ), true );
			if ( is_array( $sections ) ) {
				Options::set_post_meta( $post_id, 'course_sections', $sections );
			}
		}

		if ( isset( $_POST['lw_lms_preview_lesson_ids'] ) ) {
			$preview_ids = json_decode( sanitize_text_field( wp_unslash( $_POST['lw_lms_preview_lesson_ids'] ) ), true );
			if ( is_array( $preview_ids ) ) {
				Options::set_post_meta( $post_id, 'preview_lesson_ids', array_map( 'intval', $preview_ids ) );
			}
		}

		LessonAssignmentSaver::save( $post_id, $_POST );
	}
}
