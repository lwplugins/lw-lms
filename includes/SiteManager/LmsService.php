<?php
/**
 * LMS Service for LW Site Manager abilities.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Progress\ProgressCalculator;
use LightweightPlugins\LMS\Progress\ProgressRepository;

/**
 * Executes LMS abilities for the Site Manager.
 */
final class LmsService {

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
			$courses[] = self::format_course_summary( $post );
		}

		return [
			'success' => true,
			'courses' => $courses,
			'total'   => (int) $query->found_posts,
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

		$lessons = self::get_course_lessons( $course_id );

		return [
			'success' => true,
			'course'  => array_merge(
				self::format_course_summary( $post ),
				[
					'content'  => $post->post_content,
					'excerpt'  => $post->post_excerpt,
					'sections' => Options::get_post_meta( $course_id, 'course_sections', [] ),
					'lessons'  => $lessons,
				]
			),
		];
	}

	/**
	 * Get user progress for a course.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_progress( array $input ): array|\WP_Error {
		$user_id   = (int) ( $input['user_id'] ?? 0 );
		$course_id = (int) ( $input['course_id'] ?? 0 );

		if ( ! $user_id || ! $course_id ) {
			return new \WP_Error( 'missing_params', __( 'user_id and course_id are required.', 'lw-lms' ), [ 'status' => 400 ] );
		}

		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		if ( ! get_post( $course_id ) ) {
			return new \WP_Error( 'course_not_found', __( 'Course not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$summary   = ProgressCalculator::calculate( $user_id, $course_id );
		$raw       = ProgressRepository::get_course_progress( $user_id, $course_id );
		$by_lesson = [];

		foreach ( $raw as $row ) {
			$by_lesson[ (int) $row->lesson_id ] = [
				'status'       => $row->status,
				'completed_at' => $row->completed_at,
			];
		}

		return [
			'success'  => true,
			'progress' => [
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'percentage'        => $summary['percentage'],
				'completed_lessons' => $summary['completed_lessons'],
				'total_lessons'     => $summary['total_lessons'],
				'is_completed'      => 100 === $summary['percentage'],
				'lessons'           => $by_lesson,
			],
		];
	}

	/**
	 * Update lesson completion status for a user.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_progress( array $input ): array|\WP_Error {
		$user_id   = (int) ( $input['user_id'] ?? 0 );
		$course_id = (int) ( $input['course_id'] ?? 0 );
		$lesson_id = (int) ( $input['lesson_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $input['status'] ?? '' ) );

		$valid_statuses = [ 'completed', 'in_progress', 'not_started' ];

		if ( ! $user_id || ! $course_id || ! $lesson_id || ! $status ) {
			return new \WP_Error( 'missing_params', __( 'user_id, course_id, lesson_id, and status are required.', 'lw-lms' ), [ 'status' => 400 ] );
		}

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new \WP_Error(
				'invalid_status',
				/* translators: %s: allowed status values */
				sprintf( __( 'Invalid status. Allowed: %s.', 'lw-lms' ), implode( ', ', $valid_statuses ) ),
				[ 'status' => 400 ]
			);
		}

		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$lesson = get_post( $lesson_id );

		if ( ! $lesson || Lesson::POST_TYPE !== $lesson->post_type ) {
			return new \WP_Error( 'lesson_not_found', __( 'Lesson not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$saved = ProgressRepository::upsert( $user_id, $course_id, $lesson_id, $status );

		if ( ! $saved ) {
			return new \WP_Error( 'save_failed', __( 'Failed to update progress.', 'lw-lms' ), [ 'status' => 500 ] );
		}

		return [
			'success' => true,
			'message' => __( 'Progress updated.', 'lw-lms' ),
		];
	}

	/**
	 * Get global LW LMS plugin options.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>
	 */
	public static function get_options( array $input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by ability callback interface.
		return [
			'success' => true,
			'options' => Options::get_all(),
		];
	}

	/**
	 * Format a course post as a summary array.
	 *
	 * @param \WP_Post $post Course post object.
	 * @return array<string, mixed>
	 */
	private static function format_course_summary( \WP_Post $post ): array {
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
