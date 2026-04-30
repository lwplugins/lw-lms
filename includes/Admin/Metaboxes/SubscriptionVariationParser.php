<?php
/**
 * Subscription Variation Parser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

/**
 * Parses and builds parent_id:variation_id pair lists.
 */
final class SubscriptionVariationParser {

	/**
	 * Build textarea value from a list of parent:variation pairs.
	 *
	 * @param array $pairs List of "parent_id:variation_id" strings.
	 * @return string
	 */
	public static function build_textarea( array $pairs ): string {
		$lines = [];

		foreach ( $pairs as $pair ) {
			if ( is_string( $pair ) && preg_match( '/^\d+:\d+$/', $pair ) ) {
				$lines[] = $pair;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse subscription variations textarea input.
	 *
	 * Each line/entry: "parent_id:variation_id".
	 *
	 * @param string $raw Raw textarea content.
	 * @return array<int, string> Normalized list of "parent_id:variation_id" strings.
	 */
	public static function parse( string $raw ): array {
		$out = [];

		$tokens = preg_split( '/[\s,]+/', trim( $raw ) );

		if ( ! is_array( $tokens ) ) {
			return [];
		}

		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( ! preg_match( '/^(\d+):(\d+)$/', $token, $matches ) ) {
				continue;
			}

			$parent    = (int) $matches[1];
			$variation = (int) $matches[2];

			if ( $parent <= 0 || $variation <= 0 ) {
				continue;
			}

			$out[] = $parent . ':' . $variation;
		}

		return array_values( array_unique( $out ) );
	}
}
