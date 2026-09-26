<?php
/**
 * Tests for WooCommerce order grants.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\AccessGranter;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: the product lookup searched for `"123"` while product_ids is a
 * serialized list of ints (`i:123;`), so purchases never created access rows;
 * refunds and cancellations never revoked anything.
 *
 * @covers \LightweightPlugins\LMS\Access\AccessGranter
 */
final class AccessGranterTest extends MonkeyTestCase {

	/** @var array<int, array<string, mixed>> */
	private array $queries = [];

	protected function setUp(): void {
		parent::setUp();
		Options::clear_cache();
		$this->queries = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Options::clear_cache();
		parent::tearDown();
	}

	/**
	 * Courses 10 (ints [123]), 11 (legacy strings ["123"]) and 12 ([1234])
	 * all come back from the LIKE prefilter.
	 */
	private function catalogue(): void {
		$queries = &$this->queries;
		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) use ( &$queries ): array {
				$queries[] = $args;
				return [ 10, '11', 12 ];
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key ): mixed {
				$meta = [
					10 => [ '_lw_lms_product_ids' => [ 123 ] ],
					11 => [ '_lw_lms_product_ids' => [ '123' ] ],
					12 => [ '_lw_lms_product_ids' => [ 1234 ] ],
				];
				return $meta[ $id ][ $key ] ?? '';
			}
		);
	}

	public function test_lookup_matches_serialized_ints_and_legacy_strings(): void {
		$this->catalogue();

		$this->assertSame( [ 10, 11 ], AccessGranter::find_courses_for_product( 123 ) );

		$meta_query = $this->queries[0]['meta_query'];
		$this->assertSame( 'OR', $meta_query['relation'] );
		$this->assertSame( 'i:123;', $meta_query[0]['value'] );
		$this->assertSame( '"123"', $meta_query[1]['value'] );
	}

	public function test_hooks_grant_on_processing_and_completed_and_revoke_on_reversal(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, array $callback ) use ( &$hooks ): void {
				$hooks[ $hook ] = $callback[1];
			}
		);

		new AccessGranter();

		$this->assertSame( 'handle_order_paid', $hooks['woocommerce_order_status_processing'] );
		$this->assertSame( 'handle_order_paid', $hooks['woocommerce_order_status_completed'] );
		$this->assertSame( 'handle_order_reversed', $hooks['woocommerce_order_status_refunded'] );
		$this->assertSame( 'handle_order_reversed', $hooks['woocommerce_order_status_cancelled'] );
		$this->assertSame( 'handle_order_reversed', $hooks['woocommerce_order_status_failed'] );
	}

	public function test_paid_order_grants_each_course_once_with_the_order_as_source(): void {
		$this->catalogue();
		$this->order();
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'current_time' )->justReturn( '2026-09-26 12:00:00' );
		Functions\when( 'do_action' )->justReturn( null );

		$wpdb            = $this->wpdb( active_grant_for_course: 11 );
		$GLOBALS['wpdb'] = $wpdb;

		( new AccessGranter() )->handle_order_paid( 555 );

		// Course 11 already has an active grant from this order: skipped.
		$this->assertCount( 1, $wpdb->inserts );
		$this->assertSame( 10, $wpdb->inserts[0]['course_id'] );
		$this->assertSame( 'woocommerce', $wpdb->inserts[0]['source'] );
		$this->assertSame( 555, $wpdb->inserts[0]['source_id'] );
		$this->assertSame( 7, $wpdb->inserts[0]['user_id'] );
	}

	public function test_reversed_order_revokes_only_its_own_grants(): void {
		$this->catalogue();
		$this->order();
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$wpdb            = $this->wpdb();
		$GLOBALS['wpdb'] = $wpdb;

		( new AccessGranter() )->handle_order_reversed( 555 );

		$this->assertCount( 2, $wpdb->updates );
		foreach ( $wpdb->updates as $sql ) {
			$this->assertStringContainsString( "source = 'woocommerce'", $sql );
			$this->assertStringContainsString( 'source_id = 555', $sql );
		}
	}

	/**
	 * Order 555 by user 7 with product 123 and a fee line.
	 */
	private function order(): void {
		Functions\when( 'wc_get_order' )->justReturn(
			new class() {
				public function get_user_id(): int {
					return 7;
				}
				public function get_items(): array {
					return [ new \WC_Order_Item_Product( 123 ), (object) [ 'fee' => 1 ] ];
				}
			}
		);
	}

	/**
	 * Access table double.
	 *
	 * @param int $active_grant_for_course Course that already has an active grant from the order.
	 * @return object
	 */
	private function wpdb( int $active_grant_for_course = 0 ): object {
		return new class( $active_grant_for_course ) {
			public string $prefix = 'wp_';
			/** @var array<int, array<string, mixed>> */
			public array $inserts = [];
			/** @var array<int, string> */
			public array $updates = [];
			public function __construct( private int $active ) {}
			public function prepare( string $query, mixed ...$args ): string {
				return vsprintf( str_replace( [ '%s', '%d' ], [ "'%s'", '%d' ], $query ), $args );
			}
			public function get_var( string $query ): mixed {
				return str_contains( $query, 'course_id = ' . $this->active . ' ' ) ? '1' : null;
			}
			public function insert( string $table, array $data ): int {
				$this->inserts[] = $data;
				return 1;
			}
			public function query( string $query ): int {
				$this->updates[] = $query;
				return 1;
			}
		};
	}
}
