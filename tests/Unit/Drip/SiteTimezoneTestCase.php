<?php
/**
 * Base test case that puts WordPress in a site timezone.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Stubs wp_timezone(), wp_date() and mysql2date() with the behaviour of
 * WordPress core (7.1), so the drip time bridge is exercised against the
 * real conversions, including mysql2date( 'U' ) adding the UTC offset.
 */
abstract class SiteTimezoneTestCase extends MonkeyTestCase {

	/**
	 * Site timezones the drip schedule is checked in.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function timezones(): array {
		return [
			'UTC+2 (Europe/Budapest, summer)' => [ 'Europe/Budapest' ],
			'UTC-4 (America/New_York, summer)' => [ 'America/New_York' ],
			'UTC'                              => [ 'UTC' ],
		];
	}

	/**
	 * Put the stubbed WordPress in a site timezone.
	 *
	 * @param string $name Timezone identifier.
	 * @return DateTimeZone
	 */
	protected function use_timezone( string $name ): DateTimeZone {
		$zone = new DateTimeZone( $name );

		Functions\when( 'wp_timezone' )->justReturn( $zone );

		Functions\when( 'wp_date' )->alias(
			static fn ( string $format, int $timestamp ): string => ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);

		// Copy of core's mysql2date(), kept so the pre-2.0.2 reading is
		// tested against what WordPress really returns.
		Functions\when( 'mysql2date' )->alias(
			static function ( string $format, string $date ) use ( $zone ) {
				$datetime = date_create( $date, $zone );

				if ( false === $datetime ) {
					return false;
				}

				if ( 'G' === $format || 'U' === $format ) {
					return $datetime->getTimestamp() + $datetime->getOffset();
				}

				return $datetime->format( $format );
			}
		);

		return $zone;
	}

	/**
	 * The real Unix timestamp of a site-local wall-clock time.
	 *
	 * @param string       $local Datetime such as "2026-09-26 21:56:24".
	 * @param DateTimeZone $zone  Site timezone.
	 * @return int
	 */
	protected static function at( string $local, DateTimeZone $zone ): int {
		return ( new DateTimeImmutable( $local, $zone ) )->getTimestamp();
	}
}
