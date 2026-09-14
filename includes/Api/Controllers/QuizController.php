<?php
/**
 * Quiz REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Api\StatusPermission;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Quiz\QuizRepository;
use LightweightPlugins\LMS\Quiz\QuizSubmission;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles quiz submission (POST /lessons/{id}/quiz).
 *
 * The quiz itself is read through GET /lessons/{id}, without answers.
 */
final class QuizController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/lessons/(?P<id>\d+)/quiz',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'submit' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'id'      => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
					'answers' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Score a quiz submission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$lesson_id = (int) $request->get_param( 'id' );
		$user_id   = get_current_user_id();
		$post      = get_post( $lesson_id );

		if (
			! $post
			|| Lesson::POST_TYPE !== $post->post_type
			|| ! StatusPermission::can_read( $post->post_status, Lesson::POST_TYPE )
		) {
			return new WP_Error( 'not_found', __( 'Lesson not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		// Same gate as the lesson body.
		if ( ! AccessChecker::has_lesson_access( $lesson_id, $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this lesson.', 'lw-lms' ), [ 'status' => 403 ] );
		}

		$quiz = QuizRepository::get( $lesson_id );

		if ( null === $quiz ) {
			return new WP_Error( 'no_quiz', __( 'This lesson has no quiz.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$result = QuizSubmission::submit( $lesson_id, $user_id, $quiz, (array) $request->get_param( 'answers' ) );

		return new WP_REST_Response( $result, 200 );
	}
}
