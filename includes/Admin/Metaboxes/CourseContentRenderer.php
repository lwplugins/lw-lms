<?php
/**
 * Renders the Course Content metabox UI (sections + lessons + toolbar).
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Splits the heavy render logic out of CourseContentMetabox so each file
 * stays under the 200-line limit.
 */
final class CourseContentRenderer {

	/**
	 * Render the metabox body for the given course.
	 *
	 * @param \WP_Post $post Course post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'lw_lms_course_content', 'lw_lms_course_content_nonce' );

		$sections = Options::get_post_meta( $post->ID, 'course_sections', [] );
		$lessons  = self::fetch_course_lessons( $post->ID );
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
			<input type="hidden" name="<?php echo esc_attr( LessonAssignmentSaver::FIELD ); ?>" id="lw-lms-lesson-assignments" value="" />
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
	 * Render the content list (orphan lessons + sections with their lessons).
	 *
	 * @param array<int, array<string, mixed>> $sections Sections data.
	 * @param array<int, \WP_Post>             $lessons  Lessons.
	 * @return void
	 */
	private function render_content_list( array $sections, array $lessons ): void {
		$lessons_by_section = [];
		$orphan_lessons     = [];

		foreach ( $lessons as $lesson ) {
			$section_id = (string) Options::get_post_meta( $lesson->ID, 'lesson_section_id', '' );
			if ( '' !== $section_id ) {
				$lessons_by_section[ $section_id ][] = $lesson;
			} else {
				$orphan_lessons[] = $lesson;
			}
		}

		foreach ( $orphan_lessons as $lesson ) {
			$this->render_lesson_item( $lesson );
		}

		foreach ( $sections as $section ) {
			$this->render_section( $section, $lessons_by_section[ $section['id'] ] ?? [] );
		}
	}

	/**
	 * Render a single section block with its child lessons.
	 *
	 * @param array<string, mixed> $section Section data.
	 * @param array<int, \WP_Post> $lessons Lessons inside this section.
	 * @return void
	 */
	private function render_section( array $section, array $lessons ): void {
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
				foreach ( $lessons as $lesson ) {
					$this->render_lesson_item( $lesson );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single lesson row.
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
	 * Fetch all lessons attached to the course, ordered by lesson_order.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, \WP_Post>
	 */
	private static function fetch_course_lessons( int $course_id ): array {
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
}
