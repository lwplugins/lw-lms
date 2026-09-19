<?php
/**
 * Drip Clock.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Adds a drip delay to a moment in time.
 *
 * Days, weeks and months are calendar units evaluated in the site timezone,
 * so "3 days later" stays at the same wall-clock time across a DST switch,
 * and "1 month later" means the same day of the next month. Hours are a
 * fixed offset.
 */
final class DripClock {

	/**
	 * Site timezone the calendar units are evaluated in.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $timezone;

	/**
	 * Constructor.
	 *
	 * @param DateTimeZone $timezone Site timezone.
	 */
	public function __construct( DateTimeZone $timezone ) {
		$this->timezone = $timezone;
	}

	/**
	 * Add a rule's delay to a timestamp.
	 *
	 * @param int                                           $timestamp Unix timestamp to count from.
	 * @param array{mode: string, value: int, unit: string} $rule      Normalized rule.
	 * @return int Unix timestamp.
	 */
	public function add( int $timestamp, array $rule ): int {
		$value = $rule['value'];

		if ( $value <= 0 ) {
			return $timestamp;
		}

		if ( 'hour' === $rule['unit'] ) {
			return $timestamp + ( $value * HOUR_IN_SECONDS );
		}

		$moment = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $this->timezone );

		if ( 'month' === $rule['unit'] ) {
			return self::add_months( $moment, $value )->getTimestamp();
		}

		$days = 'week' === $rule['unit'] ? $value * 7 : $value;

		return $moment->modify( sprintf( '+%d days', $days ) )->getTimestamp();
	}

	/**
	 * Add whole months, clamping to the end of the target month.
	 *
	 * PHP's "+1 month" turns 31 January into 3 March; a learner reading
	 * "one month after enrollment" means the end of February.
	 *
	 * @param DateTimeImmutable $moment Starting moment (site timezone).
	 * @param int               $months Number of months to add.
	 * @return DateTimeImmutable
	 */
	private static function add_months( DateTimeImmutable $moment, int $months ): DateTimeImmutable {
		$day         = (int) $moment->format( 'j' );
		$first_of_it = $moment->modify( 'first day of this month' )->modify( sprintf( '+%d months', $months ) );
		$last_day    = (int) $first_of_it->format( 't' );

		return $first_of_it->setDate(
			(int) $first_of_it->format( 'Y' ),
			(int) $first_of_it->format( 'n' ),
			min( $day, $last_day )
		);
	}
}
