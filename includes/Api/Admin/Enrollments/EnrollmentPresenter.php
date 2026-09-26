<?php
/**
 * Enrollment rows for the admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Enrollments;

use LightweightPlugins\LMS\Access\EnrollmentList;
use LightweightPlugins\LMS\Api\Admin\AdminFormat;
use LightweightPlugins\LMS\Progress\ProgressCalculator;

/**
 * Turns access rows into the shape the Enrollments screen reads: learner,
 * course, source, dates, status and course progress.
 */
final class EnrollmentPresenter {

	/**
	 * A page of rows.
	 *
	 * @param array<int, object> $rows    Access rows.
	 * @param string             $now_utc Current UTC MySQL datetime.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( array $rows, string $now_utc ): array {
		AdminFormat::prime(
			array_map( static fn ( object $row ): int => (int) $row->user_id, $rows ),
			array_map( static fn ( object $row ): int => (int) $row->course_id, $rows )
		);

		return array_map( static fn ( object $row ): array => self::row( $row, $now_utc ), $rows );
	}

	/**
	 * One row.
	 *
	 * @param object $row     Access row.
	 * @param string $now_utc Current UTC MySQL datetime.
	 * @return array<string, mixed>
	 */
	public static function row( object $row, string $now_utc ): array {
		$user_id   = (int) $row->user_id;
		$course_id = (int) $row->course_id;
		$progress  = ProgressCalculator::calculate( $user_id, $course_id );

		return [
			'id'        => (int) $row->id,
			'userId'    => $user_id,
			'courseId'  => $course_id,
			'user'      => AdminFormat::user( $user_id ),
			'course'    => AdminFormat::post( $course_id ),
			'source'    => (string) $row->source,
			'sourceId'  => null === $row->source_id ? null : (int) $row->source_id,
			'grantedAt' => (string) $row->granted_at,
			'granted'   => AdminFormat::local_datetime( (string) $row->granted_at ),
			'expiresAt' => empty( $row->expires_at ) ? null : (string) $row->expires_at,
			'expires'   => AdminFormat::utc_date( empty( $row->expires_at ) ? null : (string) $row->expires_at ),
			'status'    => EnrollmentList::status_of( $row, $now_utc ),
			'progress'  => [
				'completed'  => (int) $progress['completed_lessons'],
				'total'      => (int) $progress['total_lessons'],
				'percentage' => (int) $progress['percentage'],
			],
		];
	}
}
