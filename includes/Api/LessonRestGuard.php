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
 * Keeps the core /wp/v2/lesson routes to users who can edit lessons.
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
	 * Refuse core lesson routes to users who cannot edit lessons.
	 *
	 * @param mixed            $result  Pre-dispatch result (null = continue).
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- rest_pre_dispatch signature.
		if ( null !== $result || ! self::matches( $request->get_route(), rest_get_route_for_post_type_items( Lesson::POST_TYPE ) ) ) {
			return $result;
		}

		$post_type = get_post_type_object( Lesson::POST_TYPE );

		if ( $post_type && current_user_can( $post_type->cap->edit_posts ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Lessons are available through the LMS API (/lms/v1/lessons).', 'lw-lms' ),
			[ 'status' => rest_authorization_required_code() ]
		);
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
