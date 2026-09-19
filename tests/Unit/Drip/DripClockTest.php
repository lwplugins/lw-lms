<?php
/**
 * Tests for drip delay arithmetic.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use DateTimeImmutable;
use DateTimeZone;
use LightweightPlugins\LMS\Drip\DripClock;
use LightweightPlugins\LMS\Drip\DripRule;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Drip\DripClock
 */
final class DripClockTest extends TestCase {

	public function test_hours_are_added_as_fixed_offsets(): void {
		$clock = new DripClock( new DateTimeZone( 'UTC' ) );
		$from  = self::ts( '2026-03-10 08:00:00', 'UTC' );

		$this->assertSame(
			self::ts( '2026-03-10 14:00:00', 'UTC' ),
			$clock->add( $from, self::rule( 6, 'hour' ) )
		);
	}

	public function test_days_are_calendar_days_in_the_site_timezone(): void {
		$clock = new DripClock( new DateTimeZone( 'Europe/Budapest' ) );
		$from  = self::ts( '2026-03-27 10:30:00', 'Europe/Budapest' );

		// 2026-03-29 is the DST switch in Europe/Budapest: three calendar days
		// later is still 10:30 local time, not 11:30.
		$this->assertSame(
			self::ts( '2026-03-30 10:30:00', 'Europe/Budapest' ),
			$clock->add( $from, self::rule( 3, 'day' ) )
		);
	}

	public function test_a_week_is_seven_days(): void {
		$clock = new DripClock( new DateTimeZone( 'UTC' ) );
		$from  = self::ts( '2026-01-01 09:00:00', 'UTC' );

		$this->assertSame(
			self::ts( '2026-01-15 09:00:00', 'UTC' ),
			$clock->add( $from, self::rule( 2, 'week' ) )
		);
	}

	public function test_months_are_calendar_months(): void {
		$clock = new DripClock( new DateTimeZone( 'UTC' ) );
		$from  = self::ts( '2026-01-15 09:00:00', 'UTC' );

		$this->assertSame(
			self::ts( '2026-03-15 09:00:00', 'UTC' ),
			$clock->add( $from, self::rule( 2, 'month' ) )
		);
	}

	public function test_month_end_is_clamped_instead_of_overflowing(): void {
		$clock = new DripClock( new DateTimeZone( 'UTC' ) );
		$from  = self::ts( '2026-01-31 09:00:00', 'UTC' );

		// Plain "+1 month" would land on 2026-03-03.
		$this->assertSame(
			self::ts( '2026-02-28 09:00:00', 'UTC' ),
			$clock->add( $from, self::rule( 1, 'month' ) )
		);
	}

	public function test_zero_delay_returns_the_same_moment(): void {
		$clock = new DripClock( new DateTimeZone( 'UTC' ) );
		$from  = self::ts( '2026-05-05 05:05:05', 'UTC' );

		$this->assertSame( $from, $clock->add( $from, self::rule( 0, 'day' ) ) );
	}

	/**
	 * @return array{mode: string, value: int, unit: string}
	 */
	private static function rule( int $value, string $unit ): array {
		return [
			'mode'  => DripRule::MODE_ENROLLMENT,
			'value' => $value,
			'unit'  => $unit,
		];
	}

	private static function ts( string $local, string $timezone ): int {
		return ( new DateTimeImmutable( $local, new DateTimeZone( $timezone ) ) )->getTimestamp();
	}
}
