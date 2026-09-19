<?php
/**
 * Lesson Lock Error.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\Drip\DripTime;
use LightweightPlugins\LMS\Drip\LessonLocks;
use LightweightPlugins\LMS\Drip\LessonScheduler;
use WP_Error;

/**
 * The 403 every endpoint returns for a lesson the learner is entitled to but
 * cannot open yet, so a frontend can tell "not yours" (forbidden) apart from
 * "not yet" (lesson_locked) and show the unlock date.
 */
final class LessonLockError {

	/**
	 * The error for a lesson, or null when the lesson is open.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID.
	 * @return WP_Error|null
	 */
	public static function check( int $lesson_id, int $user_id ): ?WP_Error {
		$lock = LessonLocks::for_lesson( $lesson_id, $user_id );

		return null === $lock ? null : self::from_lock( $lock );
	}

	/**
	 * Build the error from a lock.
	 *
	 * @param array{reason: string, available_at: int|null} $lock Lock data.
	 * @return WP_Error
	 */
	public static function from_lock( array $lock ): WP_Error {
		$message = LessonScheduler::REASON_SEQUENCE === $lock['reason']
			? __( 'Finish the previous lesson to open this one.', 'lw-lms' )
			: __( 'This lesson is not available yet.', 'lw-lms' );

		return new WP_Error(
			'lesson_locked',
			$message,
			[
				'status'        => 403,
				'locked_reason' => $lock['reason'],
				'available_at'  => DripTime::iso( $lock['available_at'] ),
			]
		);
	}
}
