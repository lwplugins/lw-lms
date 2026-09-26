<?php
/**
 * Courses REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Api\StatusPermission;
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
	 * Values accepted by the collection `status` parameter.
	 */
	private const STATUSES = [ 'publish', 'private', 'draft', 'any' ];

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
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_courses( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$per_page = self::per_page( $request->get_param( 'per_page' ) );
		$page     = $request->get_param( 'page' ) ?? 1;
		$category = $request->get_param( 'category' );
		$level    = $request->get_param( 'level' );
		$search   = $request->get_param( 'search' );
		$status   = (string) ( $request->get_param( 'status' ) ?? 'publish' );

		// The route is public, so non-published statuses are gated here, before
		// the query (WP_Query itself does not restrict an explicit post_status).
		if ( ! StatusPermission::can_read( $status, Course::POST_TYPE ) ) {
			return new WP_Error(
				'rest_forbidden_status',
				__( 'You are not allowed to list courses with this status.', 'lw-lms' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$args = [
			'post_type'      => Course::POST_TYPE,
			'post_status'    => $status,
			'posts_per_page' => (int) $per_page,
			'paged'          => (int) $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $category ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering.
				[
					'taxonomy' => CourseCategory::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $category,
				],
			];
		}

		if ( $level ) {
			$args['tax_query']   = $args['tax_query'] ?? []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for level filtering.
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

		// The status gate above is type-wide (edit_posts); like core REST,
		// each item is also checked, so a Contributor sees only the drafts
		// they may edit, not everyone's.
		foreach ( self::readable( $query->posts, get_current_user_id() ) as $post ) {
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
	 * The posts a user may read in their current status.
	 *
	 * @param array<int, mixed> $posts   Query results.
	 * @param int               $user_id User ID (0 = guest).
	 * @return array<int, \WP_Post>
	 */
	public static function readable( array $posts, int $user_id ): array {
		return array_values(
			array_filter(
				$posts,
				static fn ( mixed $post ): bool => $post instanceof \WP_Post && StatusPermission::can_read_post( $post, $user_id )
			)
		);
	}

	/**
	 * Page size: the requested one, else the "Courses per page" setting,
	 * kept within 1–100.
	 *
	 * @param mixed $requested per_page parameter (null when not sent).
	 * @return int
	 */
	public static function per_page( mixed $requested ): int {
		$value = null !== $requested ? (int) $requested : (int) Options::get( 'courses_per_page', 10 );

		return max( 1, min( 100, $value ) );
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
		$user_id   = get_current_user_id();

		// Non-published courses stay a 404 (not 403) for users without the
		// matching capability, so their existence is not disclosed.
		if (
			! $post
			|| Course::POST_TYPE !== $post->post_type
			|| ! StatusPermission::can_read_post( $post, $user_id )
		) {
			return new WP_Error(
				'not_found',
				__( 'Course not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		$data = CourseTransformer::transform_full( $post, $user_id );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	private function get_collection_params(): array {
		return [
			// No default here: the "Courses per page" setting is the default
			// (a route default would always win over it).
			'per_page' => [
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
			'status'   => [
				'default'           => 'publish',
				'type'              => 'string',
				'enum'              => self::STATUSES,
				'validate_callback' => function ( $param ) {
					return in_array( $param, self::STATUSES, true );
				},
			],
		];
	}
}
