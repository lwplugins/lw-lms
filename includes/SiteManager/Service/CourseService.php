<?php
/**
 * Course Service for LW Site Manager abilities.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Service;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Executes course-related abilities (list-courses, get-course).
 */
final class CourseService {

	/**
	 * List published courses with pagination.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>
	 */
	public static function list_courses( array $input ): array {
		$per_page = max( 1, (int) ( $input['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$query = new \WP_Query(
			[
				'post_type'      => Course::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$courses = [];
		foreach ( $query->posts as $post ) {
			$courses[] = self::format_summary( $post );
		}

		return [
			'success'     => true,
			'courses'     => $courses,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Get full course details with lessons.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_course( array $input ): array|\WP_Error {
		$course_id = (int) ( $input['course_id'] ?? 0 );

		if ( ! $course_id ) {
			return new \WP_Error( 'missing_course_id', __( 'course_id is required.', 'lw-lms' ), [ 'status' => 400 ] );
		}

		$post = get_post( $course_id );

		if ( ! $post || Course::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Course not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		return [
			'success' => true,
			'course'  => array_merge(
				self::format_summary( $post ),
				[
					'content'  => $post->post_content,
					'excerpt'  => $post->post_excerpt,
					'sections' => Options::get_post_meta( $course_id, 'course_sections', [] ),
					'lessons'  => self::get_course_lessons( $course_id ),
				]
			),
		];
	}

	/**
	 * Format a course post as a summary array.
	 *
	 * @param \WP_Post $post Course post object.
	 * @return array<string, mixed>
	 */
	private static function format_summary( \WP_Post $post ): array {
		$course_id = $post->ID;

		return [
			'id'          => $course_id,
			'title'       => $post->post_title,
			'status'      => $post->post_status,
			'url'         => get_permalink( $course_id ),
			'access_type' => Options::get_post_meta( $course_id, 'access_type', 'free' ),
			'duration'    => Options::get_post_meta( $course_id, 'duration', '' ),
			'instructor'  => Options::get_post_meta( $course_id, 'instructor', '' ),
		];
	}

	/**
	 * Get all published lessons for a course, ordered.
	 *
	 * @param int $course_id Course post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_course_lessons( int $course_id ): array {
		$posts = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => Options::META_PREFIX . 'lesson_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => Options::META_PREFIX . 'lesson_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		$lessons = [];
		foreach ( $posts as $lesson ) {
			$lessons[] = [
				'id'         => $lesson->ID,
				'title'      => $lesson->post_title,
				'section_id' => Options::get_post_meta( $lesson->ID, 'lesson_section_id', '' ),
				'order'      => (int) Options::get_post_meta( $lesson->ID, 'lesson_order', 0 ),
				'duration'   => Options::get_post_meta( $lesson->ID, 'duration', '' ),
			];
		}

		return $lessons;
	}
}
