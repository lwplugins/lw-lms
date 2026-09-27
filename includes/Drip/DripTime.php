<?php
/**
 * Drip Time.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use DateTimeImmutable;

/**
 * The bridge between WordPress time handling and the pure drip code:
 * the site clock, MySQL datetimes in, ISO 8601 out.
 */
final class DripTime {

	/**
	 * Clock that evaluates delays in the site timezone.
	 *
	 * @return DripClock
	 */
	public static function site_clock(): DripClock {
		return new DripClock( wp_timezone() );
	}

	/**
	 * Turn a MySQL datetime written in site-local time into a timestamp.
	 *
	 * The completed_at and granted_at columns hold current_time( 'mysql' ),
	 * so they are read in the site timezone and turned into a real Unix
	 * timestamp. Not mysql2date( 'U' ): that returns the timestamp plus the
	 * site's UTC offset, which wp_date() and the clock then applied again.
	 *
	 * @param string|null $mysql Datetime such as "2026-09-19 08:30:00".
	 * @return int|null Null when there is nothing usable to read.
	 */
	public static function from_mysql( ?string $mysql ): ?int {
		if ( null === $mysql || '' === $mysql || '0000-00-00 00:00:00' === $mysql ) {
			return null;
		}

		$datetime = date_create_immutable( $mysql, wp_timezone() );

		return false === $datetime ? null : $datetime->getTimestamp();
	}

	/**
	 * The site's UTC offset at a moment, in seconds.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return int
	 */
	public static function offset_at( int $timestamp ): int {
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->getOffset();
	}

	/**
	 * Format a timestamp for the REST payloads.
	 *
	 * ISO 8601 with the site's UTC offset, so a headless frontend can compare
	 * it with the current time without guessing a timezone.
	 *
	 * @param int|null $timestamp Unix timestamp.
	 * @return string|null
	 */
	public static function iso( ?int $timestamp ): ?string {
		if ( null === $timestamp ) {
			return null;
		}

		return (string) wp_date( DATE_ATOM, $timestamp );
	}
}
