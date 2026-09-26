<?php
/**
 * WooCommerce order lines that still count as paid.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

/**
 * Splits an order's product lines into paid ones and fully refunded ones.
 *
 * A line is fully refunded when the refunded quantity (WooCommerce stores it
 * as a negative number on the refund items) reaches the ordered quantity.
 * A partly refunded line (2 of 3) still counts as paid.
 */
final class OrderLines {

	/**
	 * Whether a product line of an order is fully refunded.
	 *
	 * @param object                 $order WC_Order.
	 * @param \WC_Order_Item_Product $item  Line item.
	 * @return bool
	 */
	public static function fully_refunded( object $order, \WC_Order_Item_Product $item ): bool {
		$quantity = (int) $item->get_quantity();

		return $quantity > 0 && abs( (int) $order->get_qty_refunded_for_item( (int) $item->get_id() ) ) >= $quantity;
	}

	/**
	 * Product IDs of the order's lines, keyed by whether the line is fully
	 * refunded.
	 *
	 * @param object $order WC_Order.
	 * @return array{paid: array<int, int>, refunded: array<int, int>}
	 */
	public static function product_ids( object $order ): array {
		$out = [
			'paid'     => [],
			'refunded' => [],
		];

		foreach ( $order->get_items() as $item ) {
			// Only line items (WC_Order_Item_Product) carry a product ID.
			if ( ! $item instanceof \WC_Order_Item_Product || ! (int) $item->get_product_id() ) {
				continue;
			}

			$out[ self::fully_refunded( $order, $item ) ? 'refunded' : 'paid' ][] = (int) $item->get_product_id();
		}

		$out['paid']     = array_values( array_unique( $out['paid'] ) );
		$out['refunded'] = array_values( array_unique( $out['refunded'] ) );

		return $out;
	}

	/**
	 * Whether a customer has a paid order with a line for one of the products
	 * (product or variation) that is not fully refunded.
	 *
	 * @param int             $user_id     User ID.
	 * @param string          $email       Billing email of guest orders.
	 * @param array<int, int> $product_ids Product IDs.
	 * @return bool
	 */
	public static function customer_has_paid_line( int $user_id, string $email, array $product_ids ): bool {
		$orders = wc_get_orders(
			[
				'customer' => array_values( array_filter( [ $user_id, $email ] ) ),
				'status'   => wc_get_is_paid_statuses(),
				'limit'    => -1,
			]
		);

		foreach ( is_array( $orders ) ? $orders : [] as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof \WC_Order_Item_Product ) {
					continue;
				}

				$ids = [ (int) $item->get_product_id(), (int) $item->get_variation_id() ];

				if ( [] !== array_intersect( $ids, $product_ids ) && ! self::fully_refunded( $order, $item ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
