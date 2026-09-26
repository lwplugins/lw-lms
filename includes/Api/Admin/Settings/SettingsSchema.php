<?php
/**
 * Validation rules of the LMS settings.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Settings;

use LightweightPlugins\LMS\Options;

/**
 * Pure data: one rule per option key. A key without an explicit rule gets
 * one from its default's type (bool → bool), so a new boolean option in
 * Options::get_defaults() is writable without touching this file.
 */
final class SettingsSchema {

	/**
	 * Explicit rules.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function rules(): array {
		return [
			'courses_per_page'     => [
				'type' => 'int',
				'min'  => 1,
				'max'  => 100,
			],
			'quiz_pass_percentage' => [
				'type' => 'int',
				'min'  => 0,
				'max'  => 100,
			],
			'default_access_type'  => [
				'type'   => 'enum',
				'values' => [ 'open', 'free', 'paid' ],
			],
		];
	}

	/**
	 * Rule of one option key, or null for an unknown key.
	 *
	 * @param string $key Option key.
	 * @return array<string, mixed>|null
	 */
	public static function rule( string $key ): ?array {
		$defaults = Options::get_defaults();

		if ( ! array_key_exists( $key, $defaults ) ) {
			return null;
		}

		$rules = self::rules();

		if ( isset( $rules[ $key ] ) ) {
			return $rules[ $key ];
		}

		if ( is_bool( $defaults[ $key ] ) ) {
			return [ 'type' => 'bool' ];
		}

		return null;
	}

	/**
	 * Allowed values of every enum option (for the admin's selects).
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function enums(): array {
		$enums = [];

		foreach ( self::rules() as $key => $rule ) {
			if ( 'enum' === $rule['type'] ) {
				$enums[ $key ] = $rule['values'];
			}
		}

		return $enums;
	}

	/**
	 * Ranges of every number option (for the admin's inputs).
	 *
	 * @return array<string, array{min: int, max: int}>
	 */
	public static function ranges(): array {
		$ranges = [];

		foreach ( self::rules() as $key => $rule ) {
			if ( 'int' === $rule['type'] ) {
				$ranges[ $key ] = [
					'min' => (int) $rule['min'],
					'max' => (int) $rule['max'],
				];
			}
		}

		return $ranges;
	}
}
