<?php
/**
 * WooCommerce Product Selector.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\WooCommerce;

/**
 * Provides product selection functionality for admin.
 */
final class ProductSelector {

	/**
	 * Get all products for selection.
	 *
	 * @param string $type Product type filter (empty for all).
	 * @return array
	 */
	public static function get_products( string $type = '' ): array {
		if ( ! WooCommerce::is_active() ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $type ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for product type filtering.
				[
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => $type,
				],
			];
		}

		$products = get_posts( $args );
		$result   = [];

		foreach ( $products as $product ) {
			$result[] = [
				'id'    => $product->ID,
				'title' => $product->post_title,
			];
		}

		return $result;
	}

	/**
	 * Get subscription products.
	 *
	 * @return array
	 */
	public static function get_subscription_products(): array {
		if ( ! WooCommerce::is_subscriptions_active() ) {
			return [];
		}

		return self::get_products( 'subscription' );
	}

	/**
	 * Get simple and variable products (non-subscription).
	 *
	 * @return array
	 */
	public static function get_regular_products(): array {
		$all     = self::get_products();
		$subs    = WooCommerce::is_subscriptions_active() ? self::get_subscription_products() : [];
		$sub_ids = array_column( $subs, 'id' );

		return array_filter(
			$all,
			function ( $product ) use ( $sub_ids ) {
				return ! in_array( $product['id'], $sub_ids, true );
			}
		);
	}
}
