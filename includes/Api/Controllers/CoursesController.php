<?php
/**
 * Courses REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Api\Transformers\CourseTransformer;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\Taxonomies\CourseCategory;
use LightweightPlugins\LMS\Taxonomies\CourseLevel;
use LightweightPlugins\LMS\Options;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles course REST endpoints.
 */
final class CoursesController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/courses',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_courses' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			]
		);

		register_rest_route(
			RestApi::NAMESPACE,
			'/courses/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_course' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Get courses collection.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_courses( WP_REST_Request $request ): WP_REST_Response {
		$per_page = $request->get_param( 'per_page' ) ?? Options::get( 'courses_per_page', 10 );
		$page     = $request->get_param( 'page' ) ?? 1;
		$category = $request->get_param( 'category' );
		$level    = $request->get_param( 'level' );
		$search   = $request->get_param( 'search' );

		$args = [
			'post_type'      => Course::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $per_page,
			'paged'          => (int) $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $category ) {
			$args['tax_query'] = [
				[
					'taxonomy' => CourseCategory::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				],
			];
		}

		if ( $level ) {
			$args['tax_query']   = $args['tax_query'] ?? [];
			$args['tax_query'][] = [
				'taxonomy' => CourseLevel::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $level,
			];
		}

		if ( $search ) {
			$args['s'] = $search;
		}

		$query   = new \WP_Query( $args );
		$courses = [];

		foreach ( $query->posts as $post ) {
			$courses[] = CourseTransformer::transform_list_item( $post );
		}

		return new WP_REST_Response(
			[
				'data' => $courses,
				'meta' => [
					'total'        => $query->found_posts,
					'pages'        => $query->max_num_pages,
					'current_page' => (int) $page,
					'per_page'     => (int) $per_page,
				],
			],
			200
		);
	}

	/**
	 * Get single course.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_course( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$course_id = (int) $request->get_param( 'id' );
		$post      = get_post( $course_id );

		if ( ! $post || Course::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Course not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error(
				'not_found',
				__( 'Course not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		$user_id = get_current_user_id();
		$data    = CourseTransformer::transform_full( $post, $user_id );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	private function get_collection_params(): array {
		return [
			'per_page' => [
				'default'           => 10,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0 && $param <= 100;
				},
			],
			'page'     => [
				'default'           => 1,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
			'category' => [
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'level'    => [
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search'   => [
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
