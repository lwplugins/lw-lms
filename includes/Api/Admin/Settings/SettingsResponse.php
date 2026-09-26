<?php
/**
 * Settings response shape.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Settings;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * The payload GET and a successful POST share: every option typed like its
 * default, plus what the screens need to render them (ranges, enums, the
 * WooCommerce state and the public API reference).
 */
final class SettingsResponse {

	/**
	 * Build the payload from storage.
	 *
	 * @return array{options: array<string, mixed>, meta: array<string, mixed>}
	 */
	public static function build(): array {
		return [
			'options' => self::options(),
			'meta'    => [
				'defaults'    => Options::get_defaults(),
				'ranges'      => SettingsSchema::ranges(),
				'enums'       => SettingsSchema::enums(),
				'woocommerce' => WooCommerce::is_active(),
				'restBase'    => rest_url( RestApi::NAMESPACE . '/' ),
				'namespace'   => RestApi::NAMESPACE,
				'endpoints'   => PublicEndpoints::all(),
			],
		];
	}

	/**
	 * Typed options.
	 *
	 * @return array<string, mixed>
	 */
	public static function options(): array {
		$stored  = Options::get_all();
		$options = [];

		foreach ( Options::get_defaults() as $key => $default ) {
			$value = $stored[ $key ] ?? $default;

			if ( is_bool( $default ) ) {
				$options[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$options[ $key ] = is_numeric( $value ) ? (int) $value : $default;
			} else {
				$options[ $key ] = is_scalar( $value ) ? (string) $value : (string) $default;
			}
		}

		return $options;
	}
}
