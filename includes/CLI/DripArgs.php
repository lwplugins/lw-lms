<?php
/**
 * Drip arguments for WP-CLI.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Drip\DripRule;

/**
 * Turns the --mode / --delay / --unit arguments into a drip rule, refusing
 * values the plugin does not understand instead of silently ignoring them.
 */
final class DripArgs {

	/**
	 * Build a rule from raw argument values.
	 *
	 * @param string        $mode          Mode argument.
	 * @param string        $value         Delay argument.
	 * @param string        $unit          Unit argument.
	 * @param array<string> $allowed_modes Modes this position accepts.
	 * @return array{mode: string, value: int, unit: string}
	 */
	public static function rule( string $mode, string $value, string $unit, array $allowed_modes ): array {
		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			\WP_CLI::error( sprintf( 'Unknown mode "%s". Use one of: %s.', $mode, implode( ', ', $allowed_modes ) ) );
		}

		if ( '' !== $unit && ! in_array( $unit, DripRule::UNITS, true ) ) {
			\WP_CLI::error( sprintf( 'Unknown unit "%s". Use one of: %s.', $unit, implode( ', ', DripRule::UNITS ) ) );
		}

		if ( '' !== $value && ( ! is_numeric( $value ) || (int) $value < 0 || (int) $value > DripRule::MAX_VALUE ) ) {
			\WP_CLI::error( sprintf( 'The delay must be a whole number between 0 and %d.', DripRule::MAX_VALUE ) );
		}

		return DripRule::normalize(
			[
				'mode'  => $mode,
				'value' => '' === $value ? 0 : (int) $value,
				'unit'  => '' === $unit ? DripRule::DEFAULT_UNIT : $unit,
			],
			$allowed_modes
		);
	}

	/**
	 * Describe a rule for CLI output.
	 *
	 * @param array{mode: string, value: int, unit: string} $rule Normalized rule.
	 * @return string
	 */
	public static function describe( array $rule ): string {
		if ( ! DripRule::is_active( $rule ) ) {
			return 'none';
		}

		$from = DripRule::MODE_PREVIOUS === $rule['mode'] ? 'after previous' : 'after enrollment';

		if ( 0 === $rule['value'] ) {
			return $from;
		}

		// CLI output is not translated, so a plain English plural is enough.
		$unit = 1 === $rule['value'] ? $rule['unit'] : $rule['unit'] . 's';

		return sprintf( '%d %s %s', $rule['value'], $unit, $from );
	}
}
