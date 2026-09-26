<?php
/**
 * Enrollments REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Privacy\EmailVisibility;
use LightweightPlugins\LMS\Access\AccessRepository;
use LightweightPlugins\LMS\Access\EnrollmentList;
use LightweightPlugins\LMS\Api\Admin\Enrollments\EnrollmentPresenter;
use LightweightPlugins\LMS\Api\Admin\Enrollments\GrantInput;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET/POST lw-lms/v1/admin/enrollments, DELETE …/enrollments/{user}/{course}.
 *
 * Grants are manual grants (AccessRepository::grant, source "manual"):
 * granting twice updates the same row. Revoking ends every active grant of
 * the learner in the course, whatever its source; revoking again is a no-op.
 */
final class EnrollmentsController {

	/**
	 * Largest accepted request body, in bytes.
	 */
	public const MAX_BYTES = 4096;

	/**
	 * Accepted list filters.
	 */
	private const FILTERS = [
		'course' => 'id',
		'user'   => 'id',
		'search' => 'text',
		'status' => [ 'active', 'expired', 'revoked' ],
		'source' => [ 'manual', 'woocommerce', 'free' ],
	];

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$permission = [ AdminRoutes::class, 'can_manage_learners' ];

		AdminRoutes::add(
			'/admin/enrollments',
			[
				WP_REST_Server::READABLE  => 'list_enrollments',
				WP_REST_Server::CREATABLE => 'grant',
			],
			$this,
			$permission
		);

		AdminRoutes::add(
			'/admin/enrollments/(?P<user>\d+)/(?P<course>\d+)',
			[ WP_REST_Server::DELETABLE => 'revoke' ],
			$this,
			$permission
		);
	}

	/**
	 * One page of enrollments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_enrollments( WP_REST_Request $request ) {
		$params = new ListParams( $request, self::FILTERS );

		if ( [] !== $params->errors ) {
			return AdminRoutes::invalid( $params->errors );
		}

		$params->filters[ EmailVisibility::SEARCH_FLAG ] = EmailVisibility::allowed();

		$now   = current_time( 'mysql', true );
		$total = EnrollmentList::count( $params->filters );
		$pages = $params->clamp( $total );
		$rows  = EnrollmentList::rows( $params->filters, $params->per_page, $params->offset() );

		return new WP_REST_Response(
			[
				'items'   => EnrollmentPresenter::rows( $rows, $now ),
				'total'   => $total,
				'page'    => $params->page,
				'perPage' => $params->per_page,
				'pages'   => $pages,
			]
		);
	}

	/**
	 * Grant a learner a course (manual source).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function grant( WP_REST_Request $request ) {
		$too_large = AdminRoutes::too_large( $request, self::MAX_BYTES );

		if ( null !== $too_large ) {
			return $too_large;
		}

		$input = new GrantInput( AdminRoutes::body( $request ), wp_date( 'Y-m-d' ) );

		if ( [] !== $input->errors ) {
			return AdminRoutes::invalid( $input->errors );
		}

		if ( ! AccessRepository::grant( $input->user_id, $input->course_id, 'manual', null, $input->expires_at ) ) {
			return AdminRoutes::error( 'lw_lms_grant_failed', __( 'The enrollment was not saved. Another plugin may have blocked it.', 'lw-lms' ), 409 );
		}

		$row = EnrollmentList::manual( $input->user_id, $input->course_id );

		return new WP_REST_Response(
			[ 'enrollment' => null === $row ? null : EnrollmentPresenter::row( $row, current_time( 'mysql', true ) ) ],
			201
		);
	}

	/**
	 * Revoke every active grant of a learner in a course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function revoke( WP_REST_Request $request ): WP_REST_Response {
		$user_id   = (int) $request->get_param( 'user' );
		$course_id = (int) $request->get_param( 'course' );

		return new WP_REST_Response(
			[
				'revoked'  => AccessRepository::revoke( $user_id, $course_id ),
				'userId'   => $user_id,
				'courseId' => $course_id,
			]
		);
	}
}
