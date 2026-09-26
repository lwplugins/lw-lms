<?php
/**
 * Tests for signed download links.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\DownloadLink;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: download_url had no REST nonce, so a logged-in learner's own
 * link answered 401. Links are now signed for the user they are issued to.
 *
 * @covers \LightweightPlugins\LMS\Api\DownloadLink
 */
final class DownloadLinkTest extends MonkeyTestCase {

	private const NOW = 1_800_000_000;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'test-salt' );
		Functions\when( 'rest_url' )->alias( static fn ( string $path ): string => 'https://example.test/wp-json/' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args )
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/**
	 * @return array<string, string>
	 */
	private static function query( string $url ): array {
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $args ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return $args;
	}

	public function test_url_points_at_the_download_route_and_verifies(): void {
		$url  = DownloadLink::url( 8, 7, self::NOW );
		$args = self::query( $url );

		$this->assertStringStartsWith( 'https://example.test/wp-json/lms/v1/download/8?', $url );
		$this->assertSame( '7', $args['lw_user'] );
		$this->assertSame( (string) ( self::NOW + 3600 ), $args['lw_expires'] );
		$this->assertTrue( DownloadLink::verify( 8, 7, (int) $args['lw_expires'], $args['lw_signature'], self::NOW + 10 ) );
	}

	public function test_link_expires(): void {
		$args = self::query( DownloadLink::url( 8, 7, self::NOW ) );

		$this->assertFalse( DownloadLink::verify( 8, 7, (int) $args['lw_expires'], $args['lw_signature'], self::NOW + 3601 ) );
	}

	public function test_signature_is_bound_to_user_attachment_and_expiry(): void {
		$args    = self::query( DownloadLink::url( 8, 7, self::NOW ) );
		$expires = (int) $args['lw_expires'];
		$sig     = $args['lw_signature'];

		$this->assertFalse( DownloadLink::verify( 8, 1, $expires, $sig, self::NOW ), 'other user' );
		$this->assertFalse( DownloadLink::verify( 9, 7, $expires, $sig, self::NOW ), 'other file' );
		$this->assertFalse( DownloadLink::verify( 8, 7, $expires + 86400, $sig, self::NOW ), 'extended expiry' );
		$this->assertFalse( DownloadLink::verify( 8, 7, $expires, '', self::NOW ), 'empty signature' );
	}

	public function test_ttl_is_filterable_with_a_one_minute_floor(): void {
		Functions\when( 'apply_filters' )->justReturn( 5 );

		$args = self::query( DownloadLink::url( 8, 7, self::NOW ) );

		$this->assertSame( (string) ( self::NOW + 60 ), $args['lw_expires'] );
	}
}
