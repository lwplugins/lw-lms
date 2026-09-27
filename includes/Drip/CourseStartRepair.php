<?php
/**
 * Course Start Repair.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use LightweightPlugins\LMS\Access\AccessQueries;

/**
 * One-shot repair of course clocks stored by 1.9.0 – 2.0.1.
 *
 * Those versions read the enrollment row's granted_at (site-local) with
 * mysql2date( 'U' ), which adds the site's UTC offset, and stored the
 * result as the learner's course start. On a site east of UTC every
 * "after enrollment" delay then opened late by the offset (west of UTC:
 * early).
 *
 * A stored start is only corrected when it is exactly the shifted value of
 * the learner's earliest grant, to the second, so clocks that came from
 * somewhere else (runtime-only access, `wp lw-lms drip set-start`) are left
 * alone. Starts set with `drip set-start --date` were shifted the same way
 * but can't be told apart from a deliberate choice; set them again.
 */
final class CourseStartRepair {

	/**
	 * Correct every shifted course start on the site.
	 *
	 * @return int Number of starts corrected.
	 */
	public static function run(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( CourseStart::META_PREFIX ) . '%'
			)
		);

		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$fixed = 0;

		foreach ( $rows as $row ) {
			$user_id   = (int) $row->user_id;
			$course_id = (int) substr( (string) $row->meta_key, strlen( CourseStart::META_PREFIX ) );

			if ( $user_id <= 0 || $course_id <= 0 || ! is_numeric( $row->meta_value ) ) {
				continue;
			}

			$corrected = self::corrected( (int) $row->meta_value, AccessQueries::get_earliest_grant( $user_id, $course_id ) );

			if ( null !== $corrected ) {
				CourseStart::set( $user_id, $course_id, $corrected );
				++$fixed;
			}
		}

		return $fixed;
	}

	/**
	 * The real course start when the stored one is the shifted grant time.
	 *
	 * @param int         $stored         Stored course start.
	 * @param string|null $earliest_grant Earliest granted_at (site-local MySQL datetime).
	 * @return int|null Corrected timestamp, or null when the stored value is not a shifted one.
	 */
	public static function corrected( int $stored, ?string $earliest_grant ): ?int {
		$granted = DripTime::from_mysql( $earliest_grant );

		if ( null === $granted ) {
			return null;
		}

		$offset = DripTime::offset_at( $granted );

		if ( 0 === $offset || $stored !== $granted + $offset ) {
			return null;
		}

		return $granted;
	}
}
