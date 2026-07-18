<?php
/**
 * Characterization tests for the access product/duration parser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Admin\Metaboxes;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Admin\Metaboxes\AccessProductParser;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Admin\Metaboxes\AccessProductParser
 */
final class AccessProductParserTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'absint' )->alias( static fn ( $value ): int => abs( (int) $value ) );
	}

	public function test_parses_ids_and_durations(): void {
		[ $ids, $durations ] = AccessProductParser::parse( "10:30\n20" );

		$this->assertSame( array( 10, 20 ), $ids );
		// Product 10 has a 30-day duration; product 20 is unlimited (absent).
		$this->assertSame( array( 10 => 30 ), $durations );
	}

	public function test_skips_blank_lines_and_zero_ids(): void {
		[ $ids, $durations ] = AccessProductParser::parse( "\n0:5\n  \n42:7" );

		$this->assertSame( array( 42 ), $ids );
		$this->assertSame( array( 42 => 7 ), $durations );
	}

	public function test_empty_input_yields_empty_arrays(): void {
		[ $ids, $durations ] = AccessProductParser::parse( '' );

		$this->assertSame( array(), $ids );
		$this->assertSame( array(), $durations );
	}

	public function test_zero_or_negative_duration_is_ignored(): void {
		[ $ids, $durations ] = AccessProductParser::parse( '15:0' );

		$this->assertSame( array( 15 ), $ids );
		$this->assertSame( array(), $durations );
	}
}
