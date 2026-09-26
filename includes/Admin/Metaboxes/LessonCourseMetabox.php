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
		<div class="lw-lms-lesson-course" data-sections="<?php echo esc_attr( (string) wp_json_encode( self::sections_by_course( $courses ) ) ); ?>">
			<p>
				<label for="lw_lms_lesson_course_id"><strong><?php esc_html_e( 'Select Course', 'lw-lms' ); ?></strong></label>
				<select id="lw_lms_lesson_course_id" name="lw_lms_lesson_course_id" class="widefat">
					<option value=""><?php esc_html_e( 'Select a course', 'lw-lms' ); ?></option>
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
					<option value=""><?php esc_html_e( 'No section', 'lw-lms' ); ?></option>
					<?php
					foreach ( $course_id ? LessonPlacement::sections( $course_id ) : [] as $id => $title ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $id ),
							selected( $section_id, $id, false ),
							esc_html( $title )
						);
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
	 * Sections of every listed course, for the section select to follow the
	 * course select (assets/js/admin.js).
	 *
	 * @param array<int, \WP_Post> $courses Courses.
	 * @return array<int, array<int, array{id: string, title: string}>>
	 */
	private static function sections_by_course( array $courses ): array {
		$map = [];

		foreach ( $courses as $course ) {
			foreach ( LessonPlacement::sections( $course->ID ) as $id => $title ) {
				$map[ $course->ID ][] = [
					'id'    => $id,
					'title' => $title,
				];
			}
		}

		return $map;
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

		// Save course ID (only a real course) and a section of that course.
		// The section ID keeps its case: sanitize_key() lowercased it, which
		// detached lessons from sections whose IDs contain capitals.
		if ( isset( $_POST['lw_lms_lesson_course_id'] ) ) {
			$course_id = LessonPlacement::course( absint( $_POST['lw_lms_lesson_course_id'] ) );
			$section   = isset( $_POST['lw_lms_lesson_section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['lw_lms_lesson_section_id'] ) ) : '';

			Options::set_post_meta( $post_id, 'lesson_course_id', $course_id );
			Options::set_post_meta( $post_id, 'lesson_section_id', LessonPlacement::section( $section, $course_id ) );
		}

		// Save order.
		if ( isset( $_POST['lw_lms_lesson_order'] ) ) {
			Options::set_post_meta( $post_id, 'lesson_order', absint( $_POST['lw_lms_lesson_order'] ) );
		}
	}
}
