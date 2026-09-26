<?php
/**
 * Tests for the attachment owner lookup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Api;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Api\AttachmentOwners;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * @covers \LightweightPlugins\LMS\Api\AttachmentOwners
 */
final class AttachmentOwnersTest extends MonkeyTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_returns_every_confirmed_owner_in_ascending_order(): void {
		Functions\when( 'maybe_unserialize' )->alias( static fn ( string $v ): mixed => unserialize( $v ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		$rows = [
			(object) [
				'post_id'    => '12',
				'meta_value' => serialize( [ [ 'id' => 8 ] ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			],
			(object) [
				'post_id'    => '30',
				'meta_value' => serialize( [ [ 'id' => '8' ] ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			],
			// A fragment hit that is not really this attachment is dropped.
			(object) [
				'post_id'    => '31',
				'meta_value' => serialize( [ [ 'id' => 81 ] ] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			],
		];

		$wpdb            = new class( $rows ) {
			public string $postmeta = 'wp_postmeta';
			public string $posts    = 'wp_posts';
			public string $query    = '';
			public function __construct( private array $rows ) {}
			public function esc_like( string $v ): string {
				return $v;
			}
			public function prepare( string $query, mixed ...$args ): string {
				return $query;
			}
			public function get_results( string $query ): array {
				$this->query = $query;
				return $this->rows;
			}
		};
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertSame( [ 12, 30 ], AttachmentOwners::find( 8 ) );
		$this->assertStringContainsString( 'ORDER BY pm.post_id ASC', $wpdb->query );
		$this->assertStringNotContainsString( 'LIMIT', $wpdb->query );
	}

	public function test_lists_matches_int_and_numeric_string_ids_only(): void {
		$this->assertTrue( AttachmentOwners::lists( [ [ 'id' => 8 ] ], 8 ) );
		$this->assertTrue( AttachmentOwners::lists( [ [ 'id' => '8' ] ], 8 ) );
		$this->assertFalse( AttachmentOwners::lists( [ [ 'id' => 81 ] ], 8 ) );
		$this->assertFalse( AttachmentOwners::lists( [ [ 'order' => 8 ] ], 8 ) );
		$this->assertFalse( AttachmentOwners::lists( 'a:0:{}', 8 ) );
	}
}
