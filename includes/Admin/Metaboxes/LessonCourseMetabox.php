<?php
/**
 * Lesson Course Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Handles the Lesson Course metabox (course selector).
 */
final class LessonCourseMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Lesson::POST_TYPE, [ $this, 'save' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_lesson_course',
			__( 'Course', 'lw-lms' ),
			[ $this, 'render' ],
			Lesson::POST_TYPE,
			'side',
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
		wp_nonce_field( 'lw_lms_lesson_course', 'lw_lms_lesson_course_nonce' );

		$course_id  = (int) Options::get_post_meta( $post->ID, 'lesson_course_id', 0 );
		$section_id = Options::get_post_meta( $post->ID, 'lesson_section_id', '' );
		$order      = (int) Options::get_post_meta( $post->ID, 'lesson_order', 0 );

		// Get course_id from URL if available (for new lessons).
		if ( ! $course_id && isset( $_GET['course_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display, no state change.
			$course_id = absint( $_GET['course_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$courses = get_posts(
			[
				'post_type'      => Course::POST_TYPE,
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		?>
		<div class="lw-lms-lesson-course">
			<p>
				<label for="lw_lms_lesson_course_id"><strong><?php esc_html_e( 'Select Course', 'lw-lms' ); ?></strong></label>
				<select id="lw_lms_lesson_course_id" name="lw_lms_lesson_course_id" class="widefat">
					<option value=""><?php esc_html_e( '— Select Course —', 'lw-lms' ); ?></option>
					<?php foreach ( $courses as $course ) : ?>
						<option value="<?php echo esc_attr( (string) $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
							<?php echo esc_html( $course->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="lw-lms-section-select" style="<?php echo ! $course_id ? 'display:none;' : ''; ?>">
				<label for="lw_lms_lesson_section_id"><strong><?php esc_html_e( 'Section (optional)', 'lw-lms' ); ?></strong></label>
				<select id="lw_lms_lesson_section_id" name="lw_lms_lesson_section_id" class="widefat">
					<option value=""><?php esc_html_e( '— No Section —', 'lw-lms' ); ?></option>
					<?php
					if ( $course_id ) {
						$sections = Options::get_post_meta( $course_id, 'course_sections', [] );
						foreach ( $sections as $section ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $section['id'] ),
								selected( $section_id, $section['id'], false ),
								esc_html( $section['title'] )
							);
						}
					}
					?>
				</select>
			</p>

			<p>
				<label for="lw_lms_lesson_order"><strong><?php esc_html_e( 'Order', 'lw-lms' ); ?></strong></label>
				<input type="number" id="lw_lms_lesson_order" name="lw_lms_lesson_order" value="<?php echo esc_attr( (string) $order ); ?>" class="widefat" min="0" />
			</p>
		</div>
		<?php
	}

	/**
	 * Save the metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['lw_lms_lesson_course_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_lesson_course_nonce'] ), 'lw_lms_lesson_course' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save course ID.
		if ( isset( $_POST['lw_lms_lesson_course_id'] ) ) {
			Options::set_post_meta( $post_id, 'lesson_course_id', absint( $_POST['lw_lms_lesson_course_id'] ) );
		}

		// Save section ID.
		if ( isset( $_POST['lw_lms_lesson_section_id'] ) ) {
			Options::set_post_meta( $post_id, 'lesson_section_id', sanitize_key( $_POST['lw_lms_lesson_section_id'] ) );
		}

		// Save order.
		if ( isset( $_POST['lw_lms_lesson_order'] ) ) {
			Options::set_post_meta( $post_id, 'lesson_order', absint( $_POST['lw_lms_lesson_order'] ) );
		}
	}
}
