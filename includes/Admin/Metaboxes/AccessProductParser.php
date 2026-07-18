<?php
/**
 * Access Product Parser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

/**
 * Parses and builds product:duration textarea format.
 */
final class AccessProductParser {

	/**
	 * Build textarea value from product IDs and durations.
	 *
	 * @param array $product_ids Product IDs.
	 * @param array $durations   Product durations map.
	 * @return string
	 */
	public static function build_textarea( array $product_ids, array $durations ): string {
		$lines = [];

		foreach ( $product_ids as $product_id ) {
			$key     = (string) $product_id;
			$days    = isset( $durations[ $key ] ) ? (int) $durations[ $key ] : 0;
			$lines[] = $days > 0 ? "{$product_id}:{$days}" : (string) $product_id;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse products textarea input.
	 *
	 * Each line: "product_id:days" or "product_id" (unlimited).
	 *
	 * @param string $raw Raw textarea content.
	 * @return array{0: array<int>, 1: array<int, int>}
	 */
	public static function parse( string $raw ): array {
		$product_ids = [];
		$durations   = [];

		foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $line ) {
			$parts      = explode( ':', $line, 2 );
			$product_id = absint( $parts[0] );

			if ( ! $product_id ) {
				continue;
			}

			$product_ids[] = $product_id;

			if ( isset( $parts[1] ) && absint( $parts[1] ) > 0 ) {
				$durations[ (string) $product_id ] = absint( $parts[1] );
			}
		}

		return [ $product_ids, $durations ];
	}
}
