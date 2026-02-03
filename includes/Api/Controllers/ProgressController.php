<?php
/**
 * Progress REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Api\Transformers\ProgressTransformer;
use LightweightPlugins\LMS\Progress\ProgressRepository;
use LightweightPlugins\LMS\Progress\ProgressCalculator;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Options;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles progress REST endpoints.
 */
final class ProgressController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/progress',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_progress' ],
					'permission_callback' => [ $this, 'check_auth' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_progress' ],
					'permission_callback' => [ $this, 'check_auth' ],
					'args'                => $this->get_update_args(),
				],
			]
		);

		register_rest_route(
			RestApi::NAMESPACE,
			'/progress/course/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_course_progress' ],
				'permission_callback' => [ $this, 'check_auth' ],
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
	 * Check if user is authenticated.
	 *
	 * @return bool|WP_Error
	 */
	public function check_auth(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'Authentication required.', 'lw-lms' ),
				[ 'status' => 401 ]
			);
		}
		return true;
	}

	/**
	 * Get all user progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_progress( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by REST API callback signature.
		$user_id  = get_current_user_id();
		$progress = ProgressRepository::get_user_progress( $user_id );

		return new WP_REST_Response(
			[
				'data' => ProgressTransformer::transform_collection( $progress ),
			],
			200
		);
	}

	/**
	 * Get progress for a specific course.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_course_progress( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = get_current_user_id();
		$course_id = (int) $request->get_param( 'id' );

		$progress = ProgressRepository::get_course_progress( $user_id, $course_id );
		$stats    = ProgressCalculator::calculate( $user_id, $course_id );

		return new WP_REST_Response(
			[
				'data'            => ProgressTransformer::transform_collection( $progress ),
				'course_progress' => $stats,
			],
			200
		);
	}

	/**
	 * Update lesson progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_progress( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id   = get_current_user_id();
		$course_id = (int) $request->get_param( 'course_id' );
		$lesson_id = (int) $request->get_param( 'lesson_id' );
		$status    = $request->get_param( 'status' );

		// Check access.
		if ( ! AccessChecker::has_lesson_access( $lesson_id, $user_id ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have access to this lesson.', 'lw-lms' ),
				[ 'status' => 403 ]
			);
		}

		// Validate course-lesson relationship.
		$lesson_course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );
		if ( $lesson_course_id !== $course_id ) {
			return new WP_Error(
				'invalid_request',
				__( 'Lesson does not belong to the specified course.', 'lw-lms' ),
				[ 'status' => 400 ]
			);
		}

		// Update progress.
		$result = ProgressRepository::upsert( $user_id, $course_id, $lesson_id, $status );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update progress.', 'lw-lms' ),
				[ 'status' => 500 ]
			);
		}

		// Fire action.
		if ( 'completed' === $status ) {
			do_action( 'lw_lms_lesson_completed', $lesson_id, $user_id );

			// Check if course is completed.
			if ( ProgressCalculator::is_course_completed( $user_id, $course_id ) ) {
				do_action( 'lw_lms_course_completed', $course_id, $user_id );
			}
		}

		$progress_entry  = ProgressRepository::get( $user_id, $lesson_id );
		$course_progress = ProgressCalculator::calculate( $user_id, $course_id );

		return new WP_REST_Response(
			[
				'success'         => true,
				'data'            => ProgressTransformer::transform_single( $progress_entry ),
				'course_progress' => $course_progress,
			],
			200
		);
	}

	/**
	 * Get update arguments.
	 *
	 * @return array
	 */
	private function get_update_args(): array {
		return [
			'course_id' => [
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
			'lesson_id' => [
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
			'status'    => [
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'not_started', 'in_progress', 'completed' ], true );
				},
			],
		];
	}
}
