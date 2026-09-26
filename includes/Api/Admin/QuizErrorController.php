<?php
/**
 * Quiz save error REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Admin\Quiz\QuizSaveError;
use LightweightPlugins\LMS\PostTypes\Lesson;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /lw-lms/v1/admin/lessons/{id}/quiz-error
 *
 * In the block editor the classic metaboxes are saved in the background and
 * their HTML is not re-rendered, so a rejected quiz JSON used to fail
 * silently behind an "Updated" snackbar. The editor script asks this route
 * after every metabox save and shows the error as an editor notice.
 */
final class QuizErrorController {

	/**
	 * Admin namespace (kept out of the public lms/v1 API).
	 */
	public const NAMESPACE = AdminRoutes::NAMESPACE;

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/lessons/(?P<id>\d+)/quiz-error',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_error' ],
				'permission_callback' => [ self::class, 'can_edit' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Only users who may edit the lesson.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit( WP_REST_Request $request ): bool {
		$lesson_id = (int) $request->get_param( 'id' );

		return Lesson::POST_TYPE === get_post_type( $lesson_id ) && current_user_can( 'edit_post', $lesson_id );
	}

	/**
	 * The pending error of a lesson's last quiz save (null when it saved).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_error( WP_REST_Request $request ): WP_REST_Response {
		$rejected = QuizSaveError::get( (int) $request->get_param( 'id' ) );

		return new WP_REST_Response( [ 'error' => null === $rejected ? null : $rejected['message'] ], 200 );
	}
}
