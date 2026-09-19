<?php
/**
 * Drip Rule.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Drip;

/**
 * A drip rule says when a lesson or a section opens: either a delay counted
 * from the moment the learner got the course, or a delay counted from the
 * completion of what comes before it.
 *
 * Every rule that enters the plugin — from a metabox, the course builder
 * JSON, WP-CLI or post meta written years ago — goes through normalize(),
 * so the rest of the drip code only ever sees the canonical shape.
 */
final class DripRule {

	/**
	 * No drip: the item is not scheduled.
	 */
	public const MODE_NONE = 'none';

	/**
	 * Delay counted from the moment the learner got access to the course.
	 */
	public const MODE_ENROLLMENT = 'enrollment';

	/**
	 * Delay counted from the completion of the previous lesson or section.
	 */
	public const MODE_PREVIOUS = 'previous';

	/**
	 * Every mode a lesson or section rule may use.
	 */
	public const MODES = [ self::MODE_NONE, self::MODE_ENROLLMENT, self::MODE_PREVIOUS ];

	/**
	 * Supported delay units.
	 */
	public const UNITS = [ 'hour', 'day', 'week', 'month' ];

	/**
	 * Unit used when the stored one is not recognised.
	 */
	public const DEFAULT_UNIT = 'day';

	/**
	 * Largest accepted delay value.
	 */
	public const MAX_VALUE = 999;

	/**
	 * The canonical "not scheduled" rule.
	 *
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function none(): array {
		return [
			'mode'  => self::MODE_NONE,
			'value' => 0,
			'unit'  => self::DEFAULT_UNIT,
		];
	}

	/**
	 * Normalize stored or submitted data into a rule.
	 *
	 * @param mixed         $raw            Stored meta value or submitted data.
	 * @param array<string> $allowed_modes  Modes accepted in this position.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function normalize( mixed $raw, array $allowed_modes = self::MODES ): array {
		if ( ! is_array( $raw ) || ! isset( $raw['mode'] ) || ! is_string( $raw['mode'] ) ) {
			return self::none();
		}

		$mode = $raw['mode'];

		if ( self::MODE_NONE === $mode || ! in_array( $mode, $allowed_modes, true ) ) {
			return self::none();
		}

		$unit = isset( $raw['unit'] ) && is_string( $raw['unit'] ) && in_array( $raw['unit'], self::UNITS, true )
			? $raw['unit']
			: self::DEFAULT_UNIT;

		$value = isset( $raw['value'] ) && is_numeric( $raw['value'] ) ? (int) $raw['value'] : 0;
		$value = max( 0, min( self::MAX_VALUE, $value ) );

		return [
			'mode'  => $mode,
			'value' => $value,
			'unit'  => $unit,
		];
	}

	/**
	 * Whether a rule schedules anything at all.
	 *
	 * A rule with a mode but no delay still counts: "as soon as the previous
	 * lesson is completed" is a schedule.
	 *
	 * @param array{mode: string, value: int, unit: string} $rule Normalized rule.
	 * @return bool
	 */
	public static function is_active( array $rule ): bool {
		return self::MODE_NONE !== $rule['mode'];
	}
}
