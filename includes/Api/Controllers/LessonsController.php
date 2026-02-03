<?php
/**
 * Lessons REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Api\Transformers\LessonTransformer;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Access\AccessChecker;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles lesson REST endpoints.
 */
final class LessonsController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/lessons/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_lesson' ],
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
	 * Get single lesson.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_lesson( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$lesson_id = (int) $request->get_param( 'id' );
		$post      = get_post( $lesson_id );

		if ( ! $post || Lesson::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Lesson not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error(
				'not_found',
				__( 'Lesson not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		// Check access.
		$user_id    = get_current_user_id();
		$has_access = AccessChecker::has_lesson_access( $lesson_id, $user_id );

		if ( ! $has_access ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this lesson.', 'lw-lms' ),
				[ 'status' => 403 ]
			);
		}

		$data = LessonTransformer::transform( $post, $user_id );

		return new WP_REST_Response( $data, 200 );
	}
}
