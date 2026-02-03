<?php
/**
 * Course Content Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Handles the Course Content metabox (sections + lessons).
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
		wp_nonce_field( 'lw_lms_course_content', 'lw_lms_course_content_nonce' );

		$sections = Options::get_post_meta( $post->ID, 'course_sections', [] );
		$lessons  = $this->get_course_lessons( $post->ID );
		?>
		<div class="lw-lms-course-builder">
			<div class="lw-lms-toolbar">
				<button type="button" class="button lw-lms-add-section">
					<?php esc_html_e( '+ Add Section', 'lw-lms' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Lesson::POST_TYPE . '&course_id=' . $post->ID ) ); ?>" class="button">
					<?php esc_html_e( '+ Add Lesson', 'lw-lms' ); ?>
				</a>
			</div>

			<div class="lw-lms-content-list" id="lw-lms-content-list">
				<?php $this->render_content_list( $sections, $lessons ); ?>
			</div>

			<input type="hidden" name="lw_lms_course_sections" id="lw-lms-course-sections" value="<?php echo esc_attr( wp_json_encode( $sections ) ); ?>" />
			<input type="hidden" name="lw_lms_preview_lesson_ids" id="lw-lms-preview-lessons" value="<?php echo esc_attr( wp_json_encode( Options::get_post_meta( $post->ID, 'preview_lesson_ids', [] ) ) ); ?>" />
		</div>

		<script type="text/template" id="lw-lms-section-template">
			<div class="lw-lms-section" data-section-id="{{id}}">
				<div class="lw-lms-section-header">
					<span class="dashicons dashicons-move lw-lms-drag-handle"></span>
					<span class="lw-lms-section-title">{{title}}</span>
					<button type="button" class="button-link lw-lms-edit-section"><?php esc_html_e( 'Edit', 'lw-lms' ); ?></button>
					<button type="button" class="button-link lw-lms-delete-section"><?php esc_html_e( 'Remove', 'lw-lms' ); ?></button>
				</div>
				<div class="lw-lms-section-lessons" data-section-id="{{id}}"></div>
			</div>
		</script>
		<?php
	}

	/**
	 * Render the content list.
	 *
	 * @param array $sections Sections data.
	 * @param array $lessons  Lessons data.
	 * @return void
	 */
	private function render_content_list( array $sections, array $lessons ): void {
		// Group lessons by section.
		$lessons_by_section = [];
		$orphan_lessons     = [];

		foreach ( $lessons as $lesson ) {
			$section_id = Options::get_post_meta( $lesson->ID, 'lesson_section_id', '' );
			if ( $section_id ) {
				$lessons_by_section[ $section_id ][] = $lesson;
			} else {
				$orphan_lessons[] = $lesson;
			}
		}

		// Render orphan lessons first.
		foreach ( $orphan_lessons as $lesson ) {
			$this->render_lesson_item( $lesson );
		}

		// Render sections with their lessons.
		foreach ( $sections as $section ) {
			?>
			<div class="lw-lms-section" data-section-id="<?php echo esc_attr( $section['id'] ); ?>">
				<div class="lw-lms-section-header">
					<span class="dashicons dashicons-move lw-lms-drag-handle"></span>
					<span class="lw-lms-section-title"><?php echo esc_html( $section['title'] ); ?></span>
					<button type="button" class="button-link lw-lms-edit-section"><?php esc_html_e( 'Edit', 'lw-lms' ); ?></button>
					<button type="button" class="button-link lw-lms-delete-section"><?php esc_html_e( 'Remove', 'lw-lms' ); ?></button>
				</div>
				<div class="lw-lms-section-lessons" data-section-id="<?php echo esc_attr( $section['id'] ); ?>">
					<?php
					if ( isset( $lessons_by_section[ $section['id'] ] ) ) {
						foreach ( $lessons_by_section[ $section['id'] ] as $lesson ) {
							$this->render_lesson_item( $lesson );
						}
					}
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render a lesson item.
	 *
	 * @param \WP_Post $lesson Lesson post.
	 * @return void
	 */
	private function render_lesson_item( \WP_Post $lesson ): void {
		$preview = Options::get_post_meta( $lesson->ID, 'preview_lesson', false );
		?>
		<div class="lw-lms-lesson" data-lesson-id="<?php echo esc_attr( (string) $lesson->ID ); ?>">
			<span class="dashicons dashicons-move lw-lms-drag-handle"></span>
			<span class="lw-lms-lesson-title"><?php echo esc_html( $lesson->post_title ); ?></span>
			<label class="lw-lms-preview-label">
				<input type="checkbox" class="lw-lms-preview-checkbox" data-lesson-id="<?php echo esc_attr( (string) $lesson->ID ); ?>" <?php checked( $preview ); ?> />
				<?php esc_html_e( 'Preview', 'lw-lms' ); ?>
			</label>
			<a href="<?php echo esc_url( get_edit_post_link( $lesson->ID ) ); ?>" class="button-link"><?php esc_html_e( 'Edit', 'lw-lms' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Get lessons for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private function get_course_lessons( int $course_id ): array {
		return get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'meta_key'       => Options::META_PREFIX . 'lesson_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for lesson ordering.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for course filtering.
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
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

		// Save sections.
		if ( isset( $_POST['lw_lms_course_sections'] ) ) {
			$sections = json_decode( sanitize_text_field( wp_unslash( $_POST['lw_lms_course_sections'] ) ), true );
			if ( is_array( $sections ) ) {
				Options::set_post_meta( $post_id, 'course_sections', $sections );
			}
		}

		// Save preview lessons.
		if ( isset( $_POST['lw_lms_preview_lesson_ids'] ) ) {
			$preview_ids = json_decode( sanitize_text_field( wp_unslash( $_POST['lw_lms_preview_lesson_ids'] ) ), true );
			if ( is_array( $preview_ids ) ) {
				Options::set_post_meta( $post_id, 'preview_lesson_ids', array_map( 'intval', $preview_ids ) );
			}
		}
	}
}
