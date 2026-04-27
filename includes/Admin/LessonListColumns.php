<?php
/**
 * Custom columns for the All Lessons admin list table.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Adds two missing columns to wp-admin/edit.php?post_type=lesson:
 *
 *  - Order: the lesson_order meta (sortable).
 *  - Course: a link to the parent course's editor (clickable so admins can
 *    jump back and forth between lesson and course).
 *
 * Issue #5.
 */
final class LessonListColumns {

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_' . Lesson::POST_TYPE . '_posts_columns', [ self::class, 'register_columns' ] );
		add_action( 'manage_' . Lesson::POST_TYPE . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-' . Lesson::POST_TYPE . '_sortable_columns', [ self::class, 'register_sortable' ] );
		add_action( 'pre_get_posts', [ self::class, 'apply_order_sort' ] );
	}

	/**
	 * Register the new columns. Date column is pushed to the end.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function register_columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );

		$columns['lw_lms_order']  = __( 'Order', 'lw-lms' );
		$columns['lw_lms_course'] = __( 'Course', 'lw-lms' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render the column body.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Lesson post id.
	 * @return void
	 */
	public static function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'lw_lms_order':
				echo esc_html( (string) (int) Options::get_post_meta( $post_id, 'lesson_order', 0 ) );
				break;

			case 'lw_lms_course':
				self::render_course_link( $post_id );
				break;
		}
	}

	/**
	 * Mark Order as sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public static function register_sortable( array $columns ): array {
		$columns['lw_lms_order'] = 'lw_lms_order';
		return $columns;
	}

	/**
	 * Apply meta-based ordering for the Order column.
	 *
	 * @param \WP_Query $query Current query.
	 * @return void
	 */
	public static function apply_order_sort( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== Lesson::POST_TYPE ) {
			return;
		}

		if ( 'lw_lms_order' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', Options::META_PREFIX . 'lesson_order' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Render a clickable link to the lesson's parent course (or an em-dash
	 * when the lesson is unattached).
	 *
	 * @param int $lesson_id Lesson id.
	 * @return void
	 */
	private static function render_course_link( int $lesson_id ): void {
		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		if ( $course_id <= 0 ) {
			echo '—';
			return;
		}

		$course = get_post( $course_id );
		if ( ! $course || Course::POST_TYPE !== $course->post_type ) {
			echo '—';
			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url( (string) get_edit_post_link( $course_id ) ),
			esc_html( get_the_title( $course_id ) )
		);
	}
}
