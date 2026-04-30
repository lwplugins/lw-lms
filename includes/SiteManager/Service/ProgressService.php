<?php
/**
 * Progress Service for LW Site Manager abilities.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Service;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Progress\ProgressCalculator;
use LightweightPlugins\LMS\Progress\ProgressRepository;

/**
 * Executes progress-related abilities (get-progress, set-progress).
 */
final class ProgressService {

	private const VALID_STATUSES = [ 'completed', 'in_progress', 'not_started' ];

	/**
	 * Get user progress for a course.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_progress( array $input ): array|\WP_Error {
		$user_id   = (int) ( $input['user_id'] ?? 0 );
		$course_id = (int) ( $input['course_id'] ?? 0 );

		if ( ! $user_id || ! $course_id ) {
			return new \WP_Error( 'missing_params', __( 'user_id and course_id are required.', 'lw-lms' ), [ 'status' => 400 ] );
		}

		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		if ( ! get_post( $course_id ) ) {
			return new \WP_Error( 'course_not_found', __( 'Course not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$summary   = ProgressCalculator::calculate( $user_id, $course_id );
		$raw       = ProgressRepository::get_course_progress( $user_id, $course_id );
		$by_lesson = [];

		foreach ( $raw as $row ) {
			$by_lesson[ (int) $row->lesson_id ] = [
				'status'       => $row->status,
				'completed_at' => $row->completed_at,
			];
		}

		return [
			'success'  => true,
			'progress' => [
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'percentage'        => $summary['percentage'],
				'completed_lessons' => $summary['completed_lessons'],
				'total_lessons'     => $summary['total_lessons'],
				'is_completed'      => 100 === $summary['percentage'],
				'lessons'           => $by_lesson,
			],
		];
	}

	/**
	 * Update lesson completion status for a user.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_progress( array $input ): array|\WP_Error {
		$user_id   = (int) ( $input['user_id'] ?? 0 );
		$course_id = (int) ( $input['course_id'] ?? 0 );
		$lesson_id = (int) ( $input['lesson_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $input['status'] ?? '' ) );

		if ( ! $user_id || ! $course_id || ! $lesson_id || ! $status ) {
			return new \WP_Error( 'missing_params', __( 'user_id, course_id, lesson_id, and status are required.', 'lw-lms' ), [ 'status' => 400 ] );
		}

		if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
			return new \WP_Error(
				'invalid_status',
				/* translators: %s: allowed status values */
				sprintf( __( 'Invalid status. Allowed: %s.', 'lw-lms' ), implode( ', ', self::VALID_STATUSES ) ),
				[ 'status' => 400 ]
			);
		}

		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$lesson = get_post( $lesson_id );

		if ( ! $lesson || Lesson::POST_TYPE !== $lesson->post_type ) {
			return new \WP_Error( 'lesson_not_found', __( 'Lesson not found.', 'lw-lms' ), [ 'status' => 404 ] );
		}

		$saved = ProgressRepository::upsert( $user_id, $course_id, $lesson_id, $status );

		if ( ! $saved ) {
			return new \WP_Error( 'save_failed', __( 'Failed to update progress.', 'lw-lms' ), [ 'status' => 500 ] );
		}

		return [
			'success' => true,
			'message' => __( 'Progress updated.', 'lw-lms' ),
		];
	}
}
