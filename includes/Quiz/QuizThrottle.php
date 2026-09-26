<?php
/**
 * Quiz attempt throttle.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

use WP_Error;

/**
 * Limits how often one learner can submit one lesson's quiz.
 *
 * Every submission is scored and stored, and each answer is reported as right
 * or wrong, so unlimited rapid retries would let a learner (or a script)
 * brute-force a required quiz and fill the attempt table. Two limits apply
 * per learner and lesson, with defaults that never bother a real learner:
 *
 * - a cooldown between two submissions (15 seconds,
 *   filter `lw_lms_quiz_attempt_cooldown`);
 * - a cap on submissions in any 24 hours (20,
 *   filter `lw_lms_quiz_daily_attempt_limit`).
 *
 * Both filters receive the user and lesson ID; return 0 to turn a limit off.
 */
final class QuizThrottle {

	public const DEFAULT_COOLDOWN    = 15;
	public const DEFAULT_DAILY_LIMIT = 20;
	private const WINDOW             = 86400;

	/**
	 * The 429 error when the learner has to wait, null when they may submit.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return WP_Error|null
	 */
	public static function check( int $user_id, int $lesson_id ): ?WP_Error {
		/**
		 * Filter the minimum number of seconds between two quiz submissions
		 * of one learner on one lesson (0 = no cooldown).
		 *
		 * @since 2.0.0
		 *
		 * @param int $cooldown  Seconds. Default 15.
		 * @param int $user_id   User ID.
		 * @param int $lesson_id Lesson ID.
		 */
		$cooldown = max( 0, (int) apply_filters( 'lw_lms_quiz_attempt_cooldown', self::DEFAULT_COOLDOWN, $user_id, $lesson_id ) );

		/**
		 * Filter how many quiz submissions one learner may make on one lesson
		 * in any 24 hours (0 = unlimited).
		 *
		 * @since 2.0.0
		 *
		 * @param int $limit     Submissions. Default 20.
		 * @param int $user_id   User ID.
		 * @param int $lesson_id Lesson ID.
		 */
		$limit = max( 0, (int) apply_filters( 'lw_lms_quiz_daily_attempt_limit', self::DEFAULT_DAILY_LIMIT, $user_id, $lesson_id ) );

		if ( 0 === $cooldown && 0 === $limit ) {
			return null;
		}

		// submitted_at is stored in site-local time, so compare on that clock.
		$now     = current_time( 'mysql' );
		$summary = QuizAttempts::get( $user_id, $lesson_id );
		$recent  = $limit > 0
			? QuizAttemptQueries::recent( $user_id, $lesson_id, self::shift( $now, -self::WINDOW ) )
			: [
				'count'  => 0,
				'oldest' => null,
			];

		$retry_after = self::retry_after(
			isset( $summary['submitted_at'] ) ? (string) $summary['submitted_at'] : null,
			$recent['count'],
			$recent['oldest'],
			$now,
			$cooldown,
			$limit
		);

		if ( null === $retry_after ) {
			return null;
		}

		return new WP_Error(
			'quiz_rate_limited',
			__( 'Too many quiz attempts. Please wait before trying again.', 'lw-lms' ),
			[
				'status'      => 429,
				'retry_after' => $retry_after,
			]
		);
	}

	/**
	 * Seconds to wait, or null when a submission is allowed now.
	 *
	 * @param string|null $last_submitted Last submission (local MySQL datetime).
	 * @param int         $recent_count   Submissions in the last 24 hours.
	 * @param string|null $oldest_recent  Oldest of those (local MySQL datetime).
	 * @param string      $now            Now (local MySQL datetime).
	 * @param int         $cooldown       Cooldown in seconds (0 = off).
	 * @param int         $limit          24-hour cap (0 = off).
	 * @return int|null
	 */
	public static function retry_after( ?string $last_submitted, int $recent_count, ?string $oldest_recent, string $now, int $cooldown, int $limit ): ?int {
		$now_ts = self::timestamp( $now );
		$wait   = 0;

		if ( $cooldown > 0 && null !== $last_submitted ) {
			$wait = max( $wait, self::timestamp( $last_submitted ) + $cooldown - $now_ts );
		}

		if ( $limit > 0 && $recent_count >= $limit ) {
			$frees_at = null !== $oldest_recent ? self::timestamp( $oldest_recent ) + self::WINDOW : $now_ts + self::WINDOW;
			$wait     = max( $wait, $frees_at - $now_ts, 1 );
		}

		return $wait > 0 ? $wait : null;
	}

	/**
	 * Seconds of a MySQL datetime on a fixed clock (the zone cancels out).
	 *
	 * @param string $datetime MySQL datetime.
	 * @return int
	 */
	private static function timestamp( string $datetime ): int {
		$ts = strtotime( $datetime . ' UTC' );

		return false === $ts ? 0 : $ts;
	}

	/**
	 * Shift a MySQL datetime by some seconds.
	 *
	 * @param string $datetime MySQL datetime.
	 * @param int    $seconds  Seconds to add (negative to subtract).
	 * @return string
	 */
	private static function shift( string $datetime, int $seconds ): string {
		return gmdate( 'Y-m-d H:i:s', self::timestamp( $datetime ) + $seconds );
	}
}
