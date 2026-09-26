<?php
/**
 * Validates list filters and paging.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use WP_REST_Request;

/**
 * Turns the query string of a list route into filters, by a spec of
 * key => type. Empty values mean "no filter"; anything else that does not
 * fit its type is reported per field instead of being silently dropped.
 *
 * Types: 'id' (positive int), 'text' (search, at most 100 characters),
 * 'bool' ("1"/"0", "true"/"false"), 'date' (Y-m-d), or a list of allowed
 * strings (enum).
 */
final class ListParams {

	/**
	 * Largest page size.
	 */
	public const MAX_PER_PAGE = 100;

	/**
	 * Filters by key.
	 *
	 * @var array<string, mixed>
	 */
	public array $filters = [];

	/**
	 * Field errors: key => messages.
	 *
	 * @var array<string, array<int, string>>
	 */
	public array $errors = [];

	/**
	 * 1-based page.
	 *
	 * @var int
	 */
	public int $page = 1;

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	public int $per_page = 20;

	/**
	 * Parse a request.
	 *
	 * @param WP_REST_Request                          $request Request.
	 * @param array<string, string|array<int, string>> $spec    Key => type.
	 */
	public function __construct( WP_REST_Request $request, array $spec ) {
		foreach ( $spec as $key => $type ) {
			$raw = $request->get_param( $key );

			if ( null === $raw || '' === $raw ) {
				continue;
			}

			$value = self::value( $raw, $type );

			if ( null === $value ) {
				$this->errors[ $key ][] = __( 'This filter value is not valid.', 'lw-lms' );
				continue;
			}

			$this->filters[ $key ] = $value;
		}

		$this->page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page       = (int) $request->get_param( 'per_page' );
		$this->per_page = $per_page > 0 ? min( self::MAX_PER_PAGE, $per_page ) : 20;
	}

	/**
	 * Rows to skip for the current page.
	 *
	 * @return int
	 */
	public function offset(): int {
		return ( $this->page - 1 ) * $this->per_page;
	}

	/**
	 * Parse one value.
	 *
	 * @param mixed                     $raw  Raw value.
	 * @param string|array<int, string> $type Type.
	 * @return mixed Parsed value, or null when invalid.
	 */
	private static function value( mixed $raw, string|array $type ): mixed {
		if ( is_array( $type ) ) {
			return is_string( $raw ) && in_array( $raw, $type, true ) ? $raw : null;
		}

		if ( ! is_scalar( $raw ) ) {
			return null;
		}

		$raw = trim( (string) $raw );

		switch ( $type ) {
			case 'id':
				return 1 === preg_match( '/^\d{1,19}$/', $raw ) && (int) $raw > 0 ? (int) $raw : null;
			case 'text':
				return mb_substr( sanitize_text_field( $raw ), 0, 100 );
			case 'bool':
				$map = [
					'1'     => true,
					'true'  => true,
					'0'     => false,
					'false' => false,
				];
				return $map[ $raw ] ?? null;
			case 'date':
				return self::date( $raw );
		}

		return null;
	}

	/**
	 * A real calendar date as Y-m-d, or null.
	 *
	 * @param string $raw Raw value.
	 * @return string|null
	 */
	public static function date( string $raw ): ?string {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) ) {
			return null;
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $raw : null;
	}
}
