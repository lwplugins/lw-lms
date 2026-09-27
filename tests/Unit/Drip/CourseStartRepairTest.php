<?php
/**
 * Tests for the repair of course clocks stored shifted by the UTC offset.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use LightweightPlugins\LMS\Drip\CourseStartRepair;

/**
 * @covers \LightweightPlugins\LMS\Drip\CourseStartRepair
 */
final class CourseStartRepairTest extends SiteTimezoneTestCase {

	private const GRANTED = '2026-09-20 10:00:00';

	/**
	 * What 1.9.0 – 2.0.1 stored: mysql2date( 'U' ) of the grant.
	 *
	 * @return int
	 */
	private static function legacy(): int {
		return (int) mysql2date( 'U', self::GRANTED, false );
	}

	public function test_shifted_start_east_of_utc_is_moved_back(): void {
		$zone = $this->use_timezone( 'Europe/Budapest' );

		$this->assertSame( self::at( self::GRANTED, $zone ), CourseStartRepair::corrected( self::legacy(), self::GRANTED ) );
	}

	public function test_shifted_start_west_of_utc_is_moved_forward(): void {
		$zone = $this->use_timezone( 'America/New_York' );

		$this->assertSame( self::at( self::GRANTED, $zone ), CourseStartRepair::corrected( self::legacy(), self::GRANTED ) );
	}

	public function test_correct_start_is_left_alone(): void {
		$zone = $this->use_timezone( 'Europe/Budapest' );

		$this->assertNull( CourseStartRepair::corrected( self::at( self::GRANTED, $zone ), self::GRANTED ) );
	}

	public function test_start_from_elsewhere_is_left_alone(): void {
		$zone = $this->use_timezone( 'Europe/Budapest' );

		$this->assertNull( CourseStartRepair::corrected( self::at( '2026-09-21 08:00:00', $zone ), self::GRANTED ) );
	}

	public function test_nothing_changes_on_a_utc_site(): void {
		$this->use_timezone( 'UTC' );

		$this->assertNull( CourseStartRepair::corrected( self::legacy(), self::GRANTED ) );
	}

	public function test_nothing_changes_without_a_grant_row(): void {
		$this->use_timezone( 'Europe/Budapest' );

		$this->assertNull( CourseStartRepair::corrected( self::legacy(), null ) );
	}
}
