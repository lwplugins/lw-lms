<?php
/**
 * Lesson Locks.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Access\AdminAccess;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Progress\ProgressSnapshotRepository;

/**
 * Which lessons a learner cannot open yet, and why.
 *
 * This is a pacing layer on top of access: a learner who is not entitled to
 * the course never gets here, and everything a learner has already earned —
 * a completed lesson, a completed course, a preview lesson — stays open.
 */
final class LessonLocks {

	/**
	 * Locks per course and user for the current request.
	 *
	 * @var array<string, array<int, array{reason: string, available_at: int|null}>>
	 */
	private static array $cache = [];

	/**
	 * Locked lessons of a course for one learner.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return array<int, array{reason: string, available_at: int|null}> Keyed by lesson ID.
	 */
	public static function for_course( int $course_id, int $user_id ): array {
		$key = $course_id . ':' . $user_id;

		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$cache[ $key ] = self::evaluate( $course_id, $user_id );
		}

		return self::$cache[ $key ];
	}

	/**
	 * The lock on a single lesson, or null when it is open.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID.
	 * @return array{reason: string, available_at: int|null}|null
	 */
	public static function for_lesson( int $lesson_id, int $user_id ): ?array {
		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		if ( $course_id <= 0 ) {
			return null;
		}

		return self::for_course( $course_id, $user_id )[ $lesson_id ] ?? null;
	}

	/**
	 * Drop the memoized locks (a completion during the same request changes them).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = [];
	}

	/**
	 * Work out the locks of a course for a learner.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return array<int, array{reason: string, available_at: int|null}>
	 */
	private static function evaluate( int $course_id, int $user_id ): array {
		$locks = self::applies( $course_id, $user_id ) ? self::schedule( $course_id, $user_id ) : [];

		/**
		 * Filter the locked lessons of a course for one learner.
		 *
		 * Keyed by lesson ID; every entry is an array of `reason`
		 * ('sequence' or 'schedule') and `available_at` (Unix timestamp or
		 * null). Remove an entry to open that lesson, add one to hold it
		 * back. Empty for courses that do not drip.
		 *
		 * @since 1.9.0
		 *
		 * @param array<int, array{reason: string, available_at: int|null}> $locks     Locked lessons.
		 * @param int                                                       $course_id Course ID.
		 * @param int                                                       $user_id   User ID.
		 */
		return self::as_locks( apply_filters( 'lw_lms_lesson_locks', $locks, $course_id, $user_id ) );
	}

	/**
	 * Keep whatever a filter returned only when it is still a lock map.
	 *
	 * @param mixed $locks Value returned by the filter.
	 * @return array<int, array{reason: string, available_at: int|null}>
	 */
	private static function as_locks( mixed $locks ): array {
		if ( ! is_array( $locks ) ) {
			return [];
		}

		$clean = [];

		foreach ( $locks as $lesson_id => $lock ) {
			if ( is_array( $lock ) && isset( $lock['reason'] ) ) {
				$clean[ (int) $lesson_id ] = [
					'reason'       => (string) $lock['reason'],
					'available_at' => isset( $lock['available_at'] ) ? (int) $lock['available_at'] : null,
				];
			}
		}

		return $clean;
	}

	/**
	 * Whether drip applies to this learner and course at all.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function applies( int $course_id, int $user_id ): bool {
		if ( $user_id <= 0 || ! DripSettings::is_linear( $course_id ) ) {
			return false;
		}

		// An open course is readable without logging in, so pacing the
		// logged-in half of the audience would be theatre.
		if ( AccessChecker::ACCESS_OPEN === AccessChecker::get_access_type( $course_id ) ) {
			return false;
		}

		// Staff bypass and learners who are not entitled at all are both
		// handled by the access layer.
		if ( AdminAccess::applies( $user_id ) || ! AccessChecker::has_course_access( $course_id, $user_id ) ) {
			return false;
		}

		// Someone who already finished the course keeps it all.
		return null === ProgressSnapshotRepository::get( $user_id, $course_id );
	}

	/**
	 * Run the scheduler over the course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return array<int, array{reason: string, available_at: int|null}>
	 */
	private static function schedule( int $course_id, int $user_id ): array {
		$start     = CourseStart::ensure( $user_id, $course_id );
		$plan      = CoursePlan::build( $course_id );
		$completed = CoursePlan::completed( $user_id, $course_id, $start );

		return ( new LessonScheduler( DripTime::site_clock() ) )->locks( $plan, $start, $completed, time() );
	}
}
