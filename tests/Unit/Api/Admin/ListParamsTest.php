<?php
/**
 * Tests for list filter parsing.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api\Admin;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\Admin\ListParams;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;
use WP_REST_Request;

/**
 * @covers \LightweightPlugins\LMS\Api\Admin\ListParams
 */
final class ListParamsTest extends MonkeyTestCase {

	private const SPEC = [
		'course' => 'id',
		'search' => 'text',
		'passed' => 'bool',
		'from'   => 'date',
		'status' => [ 'active', 'revoked' ],
	];

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $v ): string => trim( strip_tags( (string) $v ) ) );
	}

	/**
	 * Parse a query.
	 *
	 * @param array<string, mixed> $query Query.
	 * @return ListParams
	 */
	private function parse( array $query ): ListParams {
		return new ListParams( new WP_REST_Request( $query ), self::SPEC );
	}

	public function test_parses_valid_filters(): void {
		$params = $this->parse(
			[
				'course' => '12',
				'search' => ' anna ',
				'passed' => '0',
				'from'   => '2026-02-28',
				'status' => 'revoked',
			]
		);

		$this->assertSame( [], $params->errors );
		$this->assertSame(
			[
				'course' => 12,
				'search' => 'anna',
				'passed' => false,
				'from'   => '2026-02-28',
				'status' => 'revoked',
			],
			$params->filters
		);
	}

	public function test_empty_values_mean_no_filter(): void {
		$params = $this->parse(
			[
				'course' => '',
				'status' => '',
			]
		);

		$this->assertSame( [], $params->filters );
		$this->assertSame( [], $params->errors );
	}

	/**
	 * @dataProvider provide_invalid
	 */
	public function test_reports_an_invalid_value_per_field( string $key, mixed $value ): void {
		$params = $this->parse( [ $key => $value ] );

		$this->assertSame( [ $key ], array_keys( $params->errors ) );
		$this->assertSame( [], $params->filters );
	}

	public static function provide_invalid(): array {
		return [
			'negative id'    => [ 'course', '-3' ],
			'zero id'        => [ 'course', '0' ],
			'word as bool'   => [ 'passed', 'maybe' ],
			'no such day'    => [ 'from', '2026-02-30' ],
			'other format'   => [ 'from', '28/02/2026' ],
			'unknown status' => [ 'status', 'expired' ],
			'array value'    => [ 'course', [ 1 ] ],
		];
	}

	public function test_caps_the_page_size_and_computes_the_offset(): void {
		$params = $this->parse(
			[
				'page'     => '3',
				'per_page' => '1000',
			]
		);

		$this->assertSame( ListParams::MAX_PER_PAGE, $params->per_page );
		$this->assertSame( 2 * ListParams::MAX_PER_PAGE, $params->offset() );
	}

	/**
	 * @dataProvider provide_clamps
	 */
	public function test_clamp_pulls_the_page_back_onto_the_last_page( int $page, int $total, int $pages, int $expected ): void {
		$params = $this->parse(
			[
				'page'     => $page,
				'per_page' => 20,
			]
		);

		$this->assertSame( $pages, $params->clamp( $total ) );
		$this->assertSame( $expected, $params->page );
	}

	public static function provide_clamps(): array {
		return [
			'last row of page 2 deleted' => [ 2, 20, 1, 1 ],
			'page inside the range'      => [ 2, 21, 2, 2 ],
			'nothing matches'            => [ 3, 0, 0, 1 ],
			'far past the end'           => [ 9, 45, 3, 3 ],
		];
	}
}
