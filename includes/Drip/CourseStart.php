<?php
/**
 * Course Start.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use LightweightPlugins\LMS\Access\AccessQueries;

/**
 * When a learner's clock started for a course — the moment every
 * "counted from enrollment" delay is measured from.
 *
 * It is kept per user in its own meta instead of read off the access table,
 * because access rows do not exist for every source (subscriptions,
 * memberships and legacy purchases are resolved at runtime), and because
 * re-granting an existing row moves its granted_at forward, which would push
 * the whole schedule back on every renewal.
 *
 * The first value wins: a re-grant, a renewal or a second source never
 * restarts a learner's schedule.
 */
final class CourseStart {

	/**
	 * User meta key prefix.
	 */
	public const META_PREFIX = '_lw_lms_course_start_';

	/**
	 * Stored start of a learner's course clock.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return int|null Unix timestamp, or null when nothing is stored yet.
	 */
	public static function get( int $user_id, int $course_id ): ?int {
		$stored = get_user_meta( $user_id, self::key( $course_id ), true );

		return is_numeric( $stored ) ? (int) $stored : null;
	}

	/**
	 * Start of a learner's course clock, storing one when it is missing.
	 *
	 * Learners enrolled before the course started dripping keep their real
	 * enrollment date: it is recovered from the earliest access row they
	 * have. Runtime-only access (subscription, membership) leaves no row, so
	 * their clock starts now.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return int Unix timestamp.
	 */
	public static function ensure( int $user_id, int $course_id ): int {
		$stored = self::get( $user_id, $course_id );

		if ( null !== $stored ) {
			return $stored;
		}

		$granted = DripTime::from_mysql( AccessQueries::get_earliest_grant( $user_id, $course_id ) );
		$start   = $granted ?? time();

		self::set( $user_id, $course_id, $start );

		return $start;
	}

	/**
	 * Store the start of a learner's course clock.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	public static function set( int $user_id, int $course_id, int $timestamp ): void {
		update_user_meta( $user_id, self::key( $course_id ), $timestamp );
	}

	/**
	 * Forget a learner's course clock, so the next access starts a new one.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return void
	 */
	public static function clear( int $user_id, int $course_id ): void {
		delete_user_meta( $user_id, self::key( $course_id ) );
	}

	/**
	 * Record the clock when access is granted, whatever the source.
	 *
	 * Hooked to lw_lms_after_grant so an enrollment that happens long before
	 * the course is switched to linear still carries its real date.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return void
	 */
	public static function on_grant( int $user_id, int $course_id ): void {
		if ( $user_id <= 0 || $course_id <= 0 ) {
			return;
		}

		self::ensure( $user_id, $course_id );
	}

	/**
	 * Meta key of a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function key( int $course_id ): string {
		return self::META_PREFIX . $course_id;
	}
}
