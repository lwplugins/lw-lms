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
 * Grants course access from WooCommerce orders and takes it back when the
 * order is refunded, cancelled or fails, or when the order line of a course
 * product is fully refunded (a partial refund of a larger order).
 *
 * Each grant is stored with source `woocommerce` and the order ID, so a
 * revocation removes only what that order granted.
 */
final class AccessGranter {

	/**
	 * Access source of order grants.
	 */
	public const SOURCE = 'woocommerce';

	/**
	 * Order statuses that grant access. `processing` covers paid orders of
	 * virtual products that are never marked completed.
	 */
	public const GRANT_STATUSES = [ 'processing', 'completed' ];

	/**
	 * Order statuses that take the order's access back.
	 */
	public const REVOKE_STATUSES = [ 'refunded', 'cancelled', 'failed' ];

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		foreach ( self::GRANT_STATUSES as $status ) {
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'handle_order_paid' ] );
		}

		foreach ( self::REVOKE_STATUSES as $status ) {
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'handle_order_reversed' ] );
		}

		// Fires after every refund, partial or full (wc_create_refund()).
		add_action( 'woocommerce_order_refunded', [ $this, 'handle_order_refunded' ] );
	}

	/**
	 * Grant the courses of a paid order.
	 *
	 * Runs on both processing and completed; an order that already granted a
	 * course is not granted again, so the grant hooks fire once per order.
	 * Fully refunded lines grant nothing.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_paid( int $order_id ): void {
		foreach ( self::order_courses( $order_id, true ) as [ $user_id, $course_id, $product_id ] ) {
			if ( AccessQueries::has_active_grant_from( $user_id, $course_id, self::SOURCE, $order_id ) ) {
				continue;
			}

			AccessRepository::grant(
				$user_id,
				$course_id,
				self::SOURCE,
				$order_id,
				self::calculate_expiry( $course_id, $product_id )
			);
		}
	}

	/**
	 * Back-compat alias of handle_order_paid() (the 1.x hook callback).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_completed( int $order_id ): void {
		$this->handle_order_paid( $order_id );
	}

	/**
	 * Revoke what a refunded, cancelled or failed order granted.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_reversed( int $order_id ): void {
		foreach ( self::order_courses( $order_id, false ) as [ $user_id, $course_id ] ) {
			AccessRepository::revoke_by_source( $user_id, $course_id, self::SOURCE, $order_id );
		}
	}

	/**
	 * After a refund, revoke the courses whose order line is now fully
	 * refunded, unless another paid line of the same order still covers
	 * that course.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_order_refunded( int $order_id ): void {
		$order   = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		$user_id = $order ? (int) $order->get_user_id() : 0;

		if ( ! $user_id ) {
			return;
		}

		$lines = OrderLines::product_ids( $order );
		$kept  = self::courses_of( $lines['paid'] );

		foreach ( array_diff( self::courses_of( $lines['refunded'] ), $kept ) as $course_id ) {
			AccessRepository::revoke_by_source( $user_id, $course_id, self::SOURCE, $order_id );
		}
	}

	/**
	 * Course IDs linked to any of the products.
	 *
	 * @param array<int, int> $product_ids Product IDs.
	 * @return array<int, int>
	 */
	private static function courses_of( array $product_ids ): array {
		$courses = [];

		foreach ( $product_ids as $product_id ) {
			$courses = array_merge( $courses, self::find_courses_for_product( $product_id ) );
		}

		return array_values( array_unique( $courses ) );
	}

	/**
	 * The (user, course, product) triples an order covers.
	 *
	 * @param int  $order_id  Order ID.
	 * @param bool $paid_only Skip fully refunded lines.
	 * @return array<int, array{0: int, 1: int, 2: int}>
	 */
	private static function order_courses( int $order_id, bool $paid_only ): array {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			return [];
		}

		$user_id = (int) $order->get_user_id();

		if ( ! $user_id ) {
			return [];
		}

		$triples = [];

		foreach ( $order->get_items() as $item ) {
			// Only line items (WC_Order_Item_Product) carry a product ID.
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product_id = (int) $item->get_product_id();

			if ( ! $product_id || ( $paid_only && OrderLines::fully_refunded( $order, $item ) ) ) {
				continue;
			}

			foreach ( self::find_courses_for_product( $product_id ) as $course_id ) {
				$triples[] = [ $user_id, $course_id, $product_id ];
			}
		}

		return $triples;
	}

	/**
	 * Find courses linked to a product.
	 *
	 * `product_ids` is stored as a serialized list of ints (`i:123;`); rows
	 * written by old versions may hold numeric strings (`"123"`). Both forms
	 * are searched, then each hit is confirmed against the decoded list so a
	 * fragment match on another number never grants a course.
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
					'relation' => 'OR',
					[
						'key'     => Options::META_PREFIX . 'product_ids',
						'value'   => sprintf( 'i:%d;', $product_id ),
						'compare' => 'LIKE',
					],
					[
						'key'     => Options::META_PREFIX . 'product_ids',
						'value'   => sprintf( '"%d"', $product_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		$matches = [];

		foreach ( array_map( 'intval', $courses ) as $course_id ) {
			$ids = Options::get_post_meta( $course_id, 'product_ids', [] );

			if ( is_array( $ids ) && in_array( $product_id, array_map( 'intval', $ids ), true ) ) {
				$matches[] = $course_id;
			}
		}

		return $matches;
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
