<?php
/**
 * Tests for merging companion-plugin additions into REST payloads.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Transformers;

use LightweightPlugins\LMS\Api\Transformers\PayloadExtension;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LightweightPlugins\LMS\Api\Transformers\PayloadExtension
 */
final class PayloadExtensionTest extends TestCase {

	public function test_appends_keys_added_by_the_filter(): void {
		$core = self::core();

		$merged = PayloadExtension::merge( $core, $core + [ 'certificate_url' => 'https://example.test/cert' ] );

		$this->assertSame( $core + [ 'certificate_url' => 'https://example.test/cert' ], $merged );
	}

	public function test_core_keys_win_over_filter_overrides(): void {
		$filtered               = self::core();
		$filtered['accessible'] = true;
		$filtered['progress']   = [ 'percentage' => 100 ];

		$merged = PayloadExtension::merge( self::core(), $filtered );

		$this->assertSame( self::core(), $merged );
	}

	public function test_core_key_with_null_value_is_not_overridden(): void {
		$core = [ 'video' => null ];

		$merged = PayloadExtension::merge( $core, [ 'video' => [ 'url' => 'https://example.test/v.mp4' ] ] );

		$this->assertSame( $core, $merged );
	}

	public function test_core_keys_removed_by_the_filter_are_kept(): void {
		$filtered = self::core();
		unset( $filtered['accessible'] );

		$merged = PayloadExtension::merge( self::core(), $filtered );

		$this->assertSame( self::core(), $merged );
	}

	/**
	 * @dataProvider provide_non_array_filter_results
	 *
	 * @param mixed $filtered Value a misbehaving callback returned.
	 */
	public function test_non_array_filter_result_yields_core_payload( $filtered ): void {
		$this->assertSame( self::core(), PayloadExtension::merge( self::core(), $filtered ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function provide_non_array_filter_results(): array {
		return [
			'null (callback without return)' => [ null ],
			'string'                         => [ 'oops' ],
			'false'                          => [ false ],
		];
	}

	/**
	 * A representative core payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function core(): array {
		return [
			'id'         => 42,
			'title'      => 'Intro',
			'accessible' => false,
			'progress'   => [ 'percentage' => 10 ],
		];
	}
}
