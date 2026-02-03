<?php
/**
 * Access Granter.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Grants access on WooCommerce order completion.
 */
final class AccessGranter {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ] );
	}

	/**
	 * Handle WooCommerce order completed.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			if ( ! $product_id ) {
				continue;
			}

			$courses = self::find_courses_for_product( $product_id );

			foreach ( $courses as $course_id ) {
				$expires_at = self::calculate_expiry( $course_id, $product_id );

				AccessRepository::grant(
					$user_id,
					$course_id,
					'woocommerce',
					$order_id,
					$expires_at
				);
			}
		}
	}

	/**
	 * Find courses linked to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int> Course IDs.
	 */
	public static function find_courses_for_product( int $product_id ): array {
		$courses = get_posts(
			[
				'post_type'      => Course::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for product lookup.
					[
						'key'     => Options::META_PREFIX . 'product_ids',
						'value'   => sprintf( '"%d"', $product_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		return array_map( 'intval', $courses );
	}

	/**
	 * Calculate expiry datetime for a course-product pair.
	 *
	 * @param int $course_id  Course ID.
	 * @param int $product_id Product ID.
	 * @return string|null Datetime string or null for unlimited.
	 */
	private static function calculate_expiry( int $course_id, int $product_id ): ?string {
		$durations = Options::get_post_meta( $course_id, 'product_durations', [] );

		if ( ! is_array( $durations ) || empty( $durations ) ) {
			return null;
		}

		$product_key = (string) $product_id;

		if ( ! isset( $durations[ $product_key ] ) ) {
			return null;
		}

		$days = (int) $durations[ $product_key ];

		if ( 0 === $days ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
	}
}
