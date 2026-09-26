<?php
/**
 * Lookup REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Privacy\EmailVisibility;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Small read-only lists for the admin's filters and pickers:
 * GET lw-lms/v1/admin/lookups/{courses,lessons,users}.
 */
final class LookupController {

	/**
	 * Most courses or lessons one list returns.
	 */
	public const MAX_POSTS = 500;

	/**
	 * Most users one search returns.
	 */
	public const MAX_USERS = 20;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$permission = [ AdminRoutes::class, 'can_manage_learners' ];

		AdminRoutes::add( '/admin/lookups/courses', [ WP_REST_Server::READABLE => 'courses' ], $this, $permission );
		AdminRoutes::add( '/admin/lookups/lessons', [ WP_REST_Server::READABLE => 'lessons' ], $this, $permission );
		AdminRoutes::add( '/admin/lookups/users', [ WP_REST_Server::READABLE => 'users' ], $this, $permission );
	}

	/**
	 * Every course that is not in the trash, by title.
	 *
	 * @return WP_REST_Response
	 */
	public function courses(): WP_REST_Response {
		return new WP_REST_Response( self::posts( Course::POST_TYPE, [] ) );
	}

	/**
	 * Lessons, optionally of one course (?course=ID), by title.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function lessons( WP_REST_Request $request ): WP_REST_Response {
		$course_id = absint( $request->get_param( 'course' ) );
		$meta      = [];

		if ( $course_id > 0 ) {
			$meta = [
				[
					'key'   => Options::META_PREFIX . 'lesson_course_id',
					'value' => $course_id,
					'type'  => 'NUMERIC',
				],
			];
		}

		return new WP_REST_Response( self::posts( Lesson::POST_TYPE, $meta ) );
	}

	/**
	 * Users whose login or display name (or email, with list_users)
	 * contains ?search=.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function users( WP_REST_Request $request ): WP_REST_Response {
		$search = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );

		if ( '' === $search ) {
			return new WP_REST_Response( [] );
		}

		$with_email = EmailVisibility::allowed();
		$users      = get_users(
			[
				'search'         => '*' . mb_substr( $search, 0, 100 ) . '*',
				'search_columns' => $with_email ? [ 'user_login', 'user_email', 'display_name' ] : [ 'user_login', 'display_name' ],
				'number'         => self::MAX_USERS,
				'orderby'        => 'display_name',
			]
		);

		$out = [];

		foreach ( $users as $user ) {
			$row = [
				'id'    => (int) $user->ID,
				'name'  => '' !== $user->display_name ? $user->display_name : $user->user_login,
				'login' => $user->user_login,
			];

			if ( $with_email ) {
				$row['email'] = $user->user_email;
			}

			$out[] = $row;
		}

		return new WP_REST_Response( $out );
	}

	/**
	 * ID, title and status of the posts of a type.
	 *
	 * @param string                           $post_type  Post type.
	 * @param array<int, array<string, mixed>> $meta_query Meta query.
	 * @return array<int, array<string, mixed>>
	 */
	private static function posts( string $post_type, array $meta_query ): array {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page' => self::MAX_POSTS,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		];

		if ( [] !== $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$out = [];

		foreach ( get_posts( $args ) as $post ) {
			$out[] = [
				'id'     => (int) $post->ID,
				'title'  => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'lw-lms' ),
				'status' => $post->post_status,
			];
		}

		return $out;
	}
}
