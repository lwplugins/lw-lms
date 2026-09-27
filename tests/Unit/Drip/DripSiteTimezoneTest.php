<?php
/**
 * End-to-end drip schedule in a non-UTC site timezone (issue #32).
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Drip;

use DateTimeZone;
use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripTime;
use LightweightPlugins\LMS\Drip\LessonScheduler;

/**
 * Times go the way they do in production: granted_at and completed_at are
 * site-local MySQL datetimes read through DripTime, the scheduler runs on the
 * site clock and available_at is reported through DripTime::iso().
 *
 * @covers \LightweightPlugins\LMS\Drip\DripTime
 * @covers \LightweightPlugins\LMS\Drip\LessonScheduler
 */
final class DripSiteTimezoneTest extends SiteTimezoneTestCase {

	private const GRANTED   = '2026-09-20 10:00:00';
	private const COMPLETED = '2026-09-26 21:56:24';

	/**
	 * @dataProvider timezones
	 */
	public function test_previous_rule_without_delay_opens_right_after_completion( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$locks = $this->locks( $this->rule( DripRule::MODE_PREVIOUS, 0 ), self::at( '2026-09-26 21:56:25', $zone ) );

		$this->assertSame( [], $locks );
	}

	/**
	 * @dataProvider timezones
	 */
	public function test_previous_rule_of_one_day_opens_a_day_after_completion( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$locks = $this->locks( $this->rule( DripRule::MODE_PREVIOUS, 1 ), self::at( '2026-09-26 21:56:25', $zone ) );

		$this->assertSame( 'schedule', $locks[2]['reason'] );
		$this->assertSame( $this->local_iso( '2026-09-27 21:56:24', $zone ), DripTime::iso( $locks[2]['available_at'] ) );
	}

	/**
	 * @dataProvider timezones
	 */
	public function test_previous_rule_of_one_day_is_open_once_the_day_has_passed( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$locks = $this->locks( $this->rule( DripRule::MODE_PREVIOUS, 1 ), self::at( '2026-09-27 21:56:24', $zone ) );

		$this->assertSame( [], $locks );
	}

	/**
	 * @dataProvider timezones
	 */
	public function test_enrollment_rule_counts_from_the_real_grant_time( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$locks = $this->locks( $this->rule( DripRule::MODE_ENROLLMENT, 7 ), self::at( '2026-09-26 22:00:00', $zone ) );

		$this->assertSame( $this->local_iso( '2026-09-27 10:00:00', $zone ), DripTime::iso( $locks[2]['available_at'] ) );
	}

	/**
	 * @dataProvider timezones
	 */
	public function test_enrollment_rule_opens_exactly_at_its_moment( string $timezone ): void {
		$zone = $this->use_timezone( $timezone );

		$locks = $this->locks( $this->rule( DripRule::MODE_ENROLLMENT, 7 ), self::at( '2026-09-27 10:00:00', $zone ) );

		$this->assertSame( [], $locks );
	}

	/**
	 * Lesson 1 completed at COMPLETED, lesson 2 carries the rule.
	 *
	 * @param array{mode: string, value: int, unit: string} $rule Rule of lesson 2.
	 * @param int                                           $now  Current timestamp.
	 * @return array<int, array{reason: string, available_at: int|null}>
	 */
	private function locks( array $rule, int $now ): array {
		$start = DripTime::from_mysql( self::GRANTED );
		$plan  = [
			'sequence'     => [
				[
					'id'      => 1,
					'section' => '',
				],
				[
					'id'      => 2,
					'section' => '',
				],
			],
			'sections'     => [],
			'lesson_rules' => [ 2 => $rule ],
			'course_delay' => DripRule::none(),
			'exempt'       => [],
		];

		$this->assertNotNull( $start );

		return ( new LessonScheduler( DripTime::site_clock() ) )->locks( $plan, $start, [ 1 => (int) DripTime::from_mysql( self::COMPLETED ) ], $now );
	}

	/**
	 * @param string $mode  Rule mode.
	 * @param int    $value Delay in days.
	 * @return array{mode: string, value: int, unit: string}
	 */
	private function rule( string $mode, int $value ): array {
		return [
			'mode'  => $mode,
			'value' => $value,
			'unit'  => 'day',
		];
	}

	/**
	 * @param string       $local Site-local datetime.
	 * @param DateTimeZone $zone  Site timezone.
	 * @return string
	 */
	private function local_iso( string $local, DateTimeZone $zone ): string {
		return ( new \DateTimeImmutable( $local, $zone ) )->format( DATE_ATOM );
	}
}
