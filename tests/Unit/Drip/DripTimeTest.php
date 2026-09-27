<?php
/**
 * Tests for the WordPress time bridge of the drip code.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use LightweightPlugins\LMS\Drip\DripTime;

/**
 * @covers \LightweightPlugins\LMS\Drip\DripTime
 */
final class DripTimeTest extends SiteTimezoneTestCase {

	/**
	 * @dataProvider timezones
	 */
	public function test_site_local_datetime_reads_as_the_real_moment( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$this->assertSame( self::at( '2026-09-26 21:56:24', $zone ), DripTime::from_mysql( '2026-09-26 21:56:24' ) );
	}

	/**
	 * @dataProvider timezones
	 */
	public function test_stored_time_comes_back_unchanged_in_iso( string $timezone ): void {
		$zone   = $this->use_timezone( $timezone );
		$offset = ( new \DateTimeImmutable( '2026-09-26 21:56:24', $zone ) )->format( 'P' );

		$this->assertSame( '2026-09-26T21:56:24' . $offset, DripTime::iso( DripTime::from_mysql( '2026-09-26 21:56:24' ) ) );
	}

	public function test_empty_values_read_as_nothing(): void {
		$this->use_timezone( 'Europe/Budapest' );

		$this->assertNull( DripTime::from_mysql( null ) );
		$this->assertNull( DripTime::from_mysql( '' ) );
		$this->assertNull( DripTime::from_mysql( '0000-00-00 00:00:00' ) );
		$this->assertNull( DripTime::from_mysql( 'not a date' ) );
	}

	public function test_offset_follows_daylight_saving(): void {
		$zone = $this->use_timezone( 'Europe/Budapest' );

		$this->assertSame( 7200, DripTime::offset_at( self::at( '2026-09-26 12:00:00', $zone ) ) );
		$this->assertSame( 3600, DripTime::offset_at( self::at( '2026-12-01 12:00:00', $zone ) ) );
	}
}
