<?php
/**
 * Base case for the admin REST tests.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use WP_Error;
use WP_REST_Response;

/**
 * Options live in $this->store; the current user holds $this->caps.
 */
abstract class AdminRestTestCase extends MonkeyTestCase {

	/**
	 * Option name => value.
	 *
	 * @var array<string, mixed>
	 */
	protected array $store = [];

	/**
	 * Capability => granted.
	 *
	 * @var array<string, bool>
	 */
	protected array $caps = [
		'manage_options' => true,
		'manage_lms'     => true,
	];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_option' )->alias(
			fn ( $name, $fallback = false ) => array_key_exists( $name, $this->store ) ? $this->store[ $name ] : $fallback
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ): bool {
				$this->store[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'current_user_can' )->alias( fn ( $cap ): bool => ! empty( $this->caps[ $cap ] ) );
		Functions\when( 'wp_parse_args' )->alias(
			static fn ( $args, $defaults = [] ): array => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => trim( strip_tags( (string) $value ) ) );
	}

	protected function tearDown(): void {
		Options::clear_cache();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Response data, or the error.
	 *
	 * @param WP_REST_Response|WP_Error $response Response.
	 * @return mixed
	 */
	protected function data( $response ) {
		return $response instanceof WP_Error ? $response : $response->get_data();
	}

	/**
	 * Assert a 400 lw_lms_invalid error with the given fields.
	 *
	 * @param mixed              $result Result.
	 * @param array<int, string> $fields Expected field keys.
	 * @return void
	 */
	protected function assertInvalid( $result, array $fields ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'lw_lms_invalid', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertSame( $fields, array_keys( $result->get_error_data()['fields'] ) );
	}
}
