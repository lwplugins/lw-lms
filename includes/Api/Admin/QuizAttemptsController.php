<?php
/**
 * Quiz attempts REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Privacy\EmailVisibility;
use LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptCsv;
use LightweightPlugins\LMS\Api\Admin\QuizAttempts\AttemptPresenter;
use LightweightPlugins\LMS\Quiz\QuizAttemptQueries;
use LightweightPlugins\LMS\Quiz\QuizAttemptRepository;
use LightweightPlugins\LMS\Quiz\QuizAttemptSearch;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin quiz attempts (lw-lms/v1/admin/quiz-attempts): list, one attempt,
 * delete, CSV export.
 *
 * Deleting attempts removes them from the results only. A lesson a learner
 * already completed by passing its quiz stays completed.
 */
final class QuizAttemptsController {

	/**
	 * Largest accepted request body, in bytes.
	 */
	public const MAX_BYTES = 16384;

	/**
	 * Most attempts one bulk delete takes.
	 */
	public const MAX_DELETE = 200;

	/**
	 * Accepted list filters.
	 */
	private const FILTERS = [
		'course' => 'id',
		'lesson' => 'id',
		'user'   => 'id',
		'search' => 'text',
		'passed' => 'bool',
		'from'   => 'date',
		'to'     => 'date',
	];

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$permission = [ AdminRoutes::class, 'can_manage_learners' ];

		AdminRoutes::add( '/admin/quiz-attempts', [ WP_REST_Server::READABLE => 'list_attempts' ], $this, $permission );
		AdminRoutes::add( '/admin/quiz-attempts/export', [ WP_REST_Server::READABLE => 'export' ], $this, $permission );
		AdminRoutes::add( '/admin/quiz-attempts/delete', [ WP_REST_Server::CREATABLE => 'delete_many' ], $this, $permission );
		AdminRoutes::add(
			'/admin/quiz-attempts/(?P<id>\d+)',
			[
				WP_REST_Server::READABLE  => 'get_attempt',
				WP_REST_Server::DELETABLE => 'delete_one',
			],
			$this,
			$permission
		);
		AdminRoutes::add( '/admin/lessons/(?P<id>\d+)/quiz-attempts', [ WP_REST_Server::DELETABLE => 'delete_lesson' ], $this, $permission );
	}

	/**
	 * One page of attempts; with a lesson filter also that lesson's summary
	 * (attempts, learners, per-question statistics).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_attempts( WP_REST_Request $request ) {
		$params = new ListParams( $request, self::FILTERS );

		if ( [] !== $params->errors ) {
			return AdminRoutes::invalid( $params->errors );
		}

		$params->filters[ EmailVisibility::SEARCH_FLAG ] = EmailVisibility::allowed();

		$total = QuizAttemptSearch::count( $params->filters );
		$pages = $params->clamp( $total );
		$data  = [
			'items'   => AttemptPresenter::rows( QuizAttemptSearch::rows( $params->filters, $params->per_page, $params->offset() ) ),
			'total'   => $total,
			'page'    => $params->page,
			'perPage' => $params->per_page,
			'pages'   => $pages,
			'summary' => null,
		];

		if ( ! empty( $params->filters['lesson'] ) ) {
			$lesson_id       = (int) $params->filters['lesson'];
			$data['summary'] = [
				'attempts'  => QuizAttemptQueries::count_for_lesson( $lesson_id ),
				'learners'  => QuizAttemptQueries::count_learners( $lesson_id ),
				'questions' => QuizAttemptQueries::question_stats( $lesson_id ),
			];
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * One attempt with its answers (answer key for administrators only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_attempt( WP_REST_Request $request ) {
		$row = QuizAttemptSearch::find( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return AdminRoutes::not_found();
		}

		return new WP_REST_Response( AttemptPresenter::detail( $row, current_user_can( 'manage_options' ) ) );
	}

	/**
	 * Delete one attempt (deleting it again is a no-op).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_one( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( [ 'deleted' => QuizAttemptRepository::delete_ids( [ (int) $request->get_param( 'id' ) ] ) ] );
	}

	/**
	 * Delete several attempts: body { ids: [ … ] }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_many( WP_REST_Request $request ) {
		$too_large = AdminRoutes::too_large( $request, self::MAX_BYTES );

		if ( null !== $too_large ) {
			return $too_large;
		}

		$ids = AdminRoutes::body( $request )['ids'] ?? null;

		if ( ! is_array( $ids ) || [] === $ids || count( $ids ) > self::MAX_DELETE || array_filter( $ids, static fn ( $id ): bool => ! is_int( $id ) || $id < 1 ) ) {
			return AdminRoutes::invalid(
				[
					/* translators: %d: largest number of attempts. */
					'ids' => [ sprintf( __( 'Select between 1 and %d attempts.', 'lw-lms' ), self::MAX_DELETE ) ],
				]
			);
		}

		return new WP_REST_Response( [ 'deleted' => QuizAttemptRepository::delete_ids( $ids ) ] );
	}

	/**
	 * Delete every attempt of a lesson.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_lesson( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( [ 'deleted' => QuizAttemptRepository::delete_for_lesson( (int) $request->get_param( 'id' ) ) ] );
	}

	/**
	 * CSV of the attempts matching the filters (newest first, at most
	 * AttemptCsv::MAX_ROWS), returned as text for the browser to save.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export( WP_REST_Request $request ) {
		$params = new ListParams( $request, self::FILTERS );

		if ( [] !== $params->errors ) {
			return AdminRoutes::invalid( $params->errors );
		}

		$params->filters[ EmailVisibility::SEARCH_FLAG ] = EmailVisibility::allowed();

		$total = QuizAttemptSearch::count( $params->filters );
		$rows  = AttemptPresenter::rows( QuizAttemptSearch::rows( $params->filters, AttemptCsv::MAX_ROWS, 0 ) );

		return new WP_REST_Response(
			[
				'filename'  => 'quiz-attempts-' . wp_date( 'Y-m-d' ) . '.csv',
				'csv'       => AttemptCsv::build( self::csv_header(), $rows, [ __( 'Yes', 'lw-lms' ), __( 'No', 'lw-lms' ) ] ),
				'rows'      => count( $rows ),
				'truncated' => $total > count( $rows ),
			]
		);
	}

	/**
	 * CSV column titles.
	 *
	 * @return array<int, string>
	 */
	private static function csv_header(): array {
		return [
			__( 'Attempt ID', 'lw-lms' ),
			__( 'Submitted', 'lw-lms' ),
			__( 'User ID', 'lw-lms' ),
			__( 'Learner', 'lw-lms' ),
			__( 'Lesson', 'lw-lms' ),
			__( 'Course', 'lw-lms' ),
			__( 'Score', 'lw-lms' ),
			__( 'Scored questions', 'lw-lms' ),
			__( 'Percentage', 'lw-lms' ),
			__( 'Passed', 'lw-lms' ),
		];
	}
}
