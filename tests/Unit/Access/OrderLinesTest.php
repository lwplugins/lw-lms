<?php
/**
 * Tests for paid and refunded WooCommerce order lines.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Tests\Unit\Access;

use Brain\Monkey\Functions;
use LightweightPlugins\LMS\Access\OrderLines;
use LightweightPlugins\LMS\Tests\Unit\MonkeyTestCase;

/**
 * Regression: a fully refunded course line of a larger order kept its access,
 * and the legacy purchase fallback still counted that order as paid.
 *
 * @covers \LightweightPlugins\LMS\Access\OrderLines
 */
final class OrderLinesTest extends MonkeyTestCase {

	/**
	 * An order double.
	 *
	 * @param array<int, object> $items    Line items.
	 * @param array<int, int>    $refunded Refunded quantity per item ID (negative).
	 * @return object
	 */
	private function order( array $items, array $refunded = [] ): object {
		return new class( $items, $refunded ) {
			public function __construct( private array $items, private array $refunded ) {}
			public function get_items(): array {
				return $this->items;
			}
			public function get_qty_refunded_for_item( int $item_id ): int {
				return $this->refunded[ $item_id ] ?? 0;
			}
		};
	}

	/**
	 * @dataProvider provide_refunds
	 */
	public function test_fully_refunded_needs_the_whole_quantity( int $quantity, int $refunded, bool $expected ): void {
		$item = new \WC_Order_Item_Product( 123, 1, $quantity );

		$this->assertSame( $expected, OrderLines::fully_refunded( $this->order( [ $item ], [ 1 => $refunded ] ), $item ) );
	}

	public static function provide_refunds(): array {
		return [
			'nothing refunded' => [ 1, 0, false ],
			'fully refunded'   => [ 1, -1, true ],
			'2 of 3 refunded'  => [ 3, -2, false ],
			'3 of 3 refunded'  => [ 3, -3, true ],
		];
	}

	public function test_product_ids_split_paid_and_refunded_lines(): void {
		$order = $this->order(
			[ new \WC_Order_Item_Product( 123, 1 ), new \WC_Order_Item_Product( 456, 2 ), (object) [ 'fee' => 1 ] ],
			[ 1 => -1 ]
		);

		$this->assertSame(
			[
				'paid'     => [ 456 ],
				'refunded' => [ 123 ],
			],
			OrderLines::product_ids( $order )
		);
	}

	public function test_legacy_purchase_ignores_fully_refunded_lines(): void {
		Functions\when( 'wc_get_is_paid_statuses' )->justReturn( [ 'processing', 'completed' ] );
		Functions\when( 'wc_get_orders' )->justReturn(
			[ $this->order( [ new \WC_Order_Item_Product( 123, 1 ), new \WC_Order_Item_Product( 456, 2 ) ], [ 1 => -1 ] ) ]
		);

		$this->assertFalse( OrderLines::customer_has_paid_line( 7, 'a@example.test', [ 123 ] ) );
		$this->assertTrue( OrderLines::customer_has_paid_line( 7, 'a@example.test', [ 456 ] ) );
	}

	public function test_legacy_purchase_matches_variations(): void {
		Functions\when( 'wc_get_is_paid_statuses' )->justReturn( [ 'completed' ] );
		Functions\when( 'wc_get_orders' )->justReturn( [ $this->order( [ new \WC_Order_Item_Product( 100, 1, 1, 123 ) ] ) ] );

		$this->assertTrue( OrderLines::customer_has_paid_line( 7, '', [ 123 ] ) );
	}
}
