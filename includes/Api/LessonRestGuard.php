<?php
/**
 * Core REST guard for lessons.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Keeps the core /wp/v2/lesson routes to users who can edit the lesson.
 *
 * The lesson CPT needs show_in_rest for the block editor, but core serves
 * every published post (body and REST-registered meta such as video) to
 * anyone — bypassing the course access check of /lms/v1/lessons/{id}.
 * Learners read lessons through the LMS API instead.
 */
final class LessonRestGuard {

	/**
	 * Hook the guard.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'rest_pre_dispatch', [ self::class, 'guard' ], 10, 3 );
	}

	/**
	 * Refuse core lesson routes to users who may not see the lesson.
	 *
	 * @param mixed            $result  Pre-dispatch result (null = continue).
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- rest_pre_dispatch signature.
		$lessons_route = rest_get_route_for_post_type_items( Lesson::POST_TYPE );

		if ( null !== $result || ! self::matches( $request->get_route(), $lessons_route ) ) {
			return $result;
		}

		if ( self::allows( $request->get_method(), $request->get_route(), $lessons_route ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Lessons are available through the LMS API (/lms/v1/lessons).', 'lw-lms' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Whether the current user may use a core lesson route.
	 *
	 * The edit_posts capability alone is not enough: Contributors and shop managers have it,
	 * and published lessons hold paid content. The rules:
	 * - manage_lms: every route;
	 * - one lesson (and its revisions/autosaves): edit_post on that lesson,
	 *   which is what the block editor needs for a lesson the user may edit;
	 * - the collection: reading needs edit_others_posts of the lesson type
	 *   (someone who can edit every lesson anyway); creating needs the
	 *   type's create_posts, and core checks the rest.
	 *
	 * @param string $method        HTTP method.
	 * @param string $route         Requested route.
	 * @param string $lessons_route Core lesson collection route.
	 * @return bool
	 */
	public static function allows( string $method, string $route, string $lessons_route ): bool {
		if ( current_user_can( 'manage_lms' ) ) {
			return true;
		}

		$lesson_id = self::lesson_id( $route, $lessons_route );

		if ( $lesson_id > 0 ) {
			return current_user_can( 'edit_post', $lesson_id );
		}

		$post_type = get_post_type_object( Lesson::POST_TYPE );

		if ( ! $post_type ) {
			return false;
		}

		if ( 'GET' === strtoupper( $method ) || 'HEAD' === strtoupper( $method ) ) {
			return current_user_can( $post_type->cap->edit_others_posts );
		}

		return current_user_can( $post_type->cap->create_posts );
	}

	/**
	 * The lesson ID of a single-lesson route (0 for the collection).
	 *
	 * @param string $route         Requested route.
	 * @param string $lessons_route Core lesson collection route.
	 * @return int
	 */
	public static function lesson_id( string $route, string $lessons_route ): int {
		$rest = substr( strtolower( rtrim( $route, '/' ) ), strlen( strtolower( $lessons_route ) ) );

		return 1 === preg_match( '#^/(\d+)(?:/|$)#', $rest, $matches ) ? (int) $matches[1] : 0;
	}

	/**
	 * Whether a request route is one of the core lesson routes.
	 *
	 * WP_REST_Server matches routes case-insensitively, so the comparison is
	 * lowercased; a trailing slash is ignored.
	 *
	 * @param string $route         Requested route.
	 * @param string $lessons_route Core lesson collection route ('' = not in REST).
	 * @return bool
	 */
	public static function matches( string $route, string $lessons_route ): bool {
		if ( '' === $lessons_route ) {
			return false;
		}

		$route         = strtolower( rtrim( $route, '/' ) );
		$lessons_route = strtolower( $lessons_route );

		return $route === $lessons_route || str_starts_with( $route, $lessons_route . '/' );
	}
}
