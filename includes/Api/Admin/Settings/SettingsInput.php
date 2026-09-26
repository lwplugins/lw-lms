<?php
/**
 * Validates a settings save request.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Settings;

/**
 * Checks a whole partial body ({ option_key: value }) before anything is
 * written. Keys that are not sent are never touched, so a switch that is not
 * sent is never turned off.
 */
final class SettingsInput {

	/**
	 * Field errors: key => messages.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $errors = [];

	/**
	 * Validated changes.
	 *
	 * @var array<string, mixed>
	 */
	private array $values = [];

	/**
	 * Validate a body.
	 *
	 * @param array<array-key, mixed> $body Decoded JSON body.
	 */
	public function __construct( array $body ) {
		foreach ( $body as $key => $value ) {
			$key  = (string) $key;
			$rule = SettingsSchema::rule( $key );

			if ( null === $rule ) {
				$this->errors[ $key ][] = __( 'Unknown setting.', 'lw-lms' );
				continue;
			}

			$error = $this->parse( $key, $rule, $value );

			if ( null !== $error ) {
				$this->errors[ $key ][] = $error;
			}
		}
	}

	/**
	 * Field errors (empty when the body is valid).
	 *
	 * @return array<string, array<int, string>>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Validated changes, keyed by option (empty while any field is invalid).
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		return [] === $this->errors ? $this->values : [];
	}

	/**
	 * Parse one value by its rule.
	 *
	 * @param string               $key   Option key.
	 * @param array<string, mixed> $rule  Rule.
	 * @param mixed                $value Submitted value.
	 * @return string|null Error message, or null when the value was stored.
	 */
	private function parse( string $key, array $rule, mixed $value ): ?string {
		switch ( $rule['type'] ) {
			case 'bool':
				if ( ! is_bool( $value ) ) {
					return __( 'Must be on or off.', 'lw-lms' );
				}
				$this->values[ $key ] = $value;
				return null;

			case 'int':
				return $this->parse_int( $key, $rule, $value );

			case 'enum':
				if ( ! is_string( $value ) || ! in_array( $value, $rule['values'], true ) ) {
					return __( 'Choose one of the listed options.', 'lw-lms' );
				}
				$this->values[ $key ] = $value;
				return null;
		}

		return __( 'Unknown setting.', 'lw-lms' );
	}

	/**
	 * A whole number within the rule's range (a numeric string is accepted).
	 *
	 * @param string               $key   Option key.
	 * @param array<string, mixed> $rule  Rule with min and max.
	 * @param mixed                $value Submitted value.
	 * @return string|null Error message, or null when the value was stored.
	 */
	private function parse_int( string $key, array $rule, mixed $value ): ?string {
		$number = null;

		if ( is_int( $value ) ) {
			$number = $value;
		} elseif ( is_string( $value ) && 1 === preg_match( '/^\s*-?\d{1,9}\s*$/', $value ) ) {
			$number = (int) trim( $value );
		}

		if ( null === $number || $number < $rule['min'] || $number > $rule['max'] ) {
			return sprintf(
				/* translators: 1: smallest allowed number, 2: largest allowed number. */
				__( 'Enter a whole number from %1$d to %2$d.', 'lw-lms' ),
				$rule['min'],
				$rule['max']
			);
		}

		$this->values[ $key ] = $number;

		return null;
	}
}
