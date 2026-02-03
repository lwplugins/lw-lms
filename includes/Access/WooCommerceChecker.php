<?php
/**
 * WooCommerce Access Checker.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Checks WooCommerce purchases and subscriptions.
 */
final class WooCommerceChecker {

	/**
	 * Check if user has purchased access to a course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function has_purchased( int $course_id, int $user_id ): bool {
		if ( ! WooCommerce::is_active() ) {
			return false;
		}

		// Check single purchases.
		if ( self::has_product_purchase( $course_id, $user_id ) ) {
			return true;
		}

		// Check subscriptions.
		if ( self::has_active_subscription( $course_id, $user_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user has purchased a product linked to the course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function has_product_purchase( int $course_id, int $user_id ): bool {
		if ( ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}

		$product_ids = Options::get_post_meta( $course_id, 'product_ids', [] );

		if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( $product_ids as $product_id ) {
			if ( wc_customer_bought_product( $user->user_email, $user_id, (int) $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user has an active subscription linked to the course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function has_active_subscription( int $course_id, int $user_id ): bool {
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			return false;
		}

		$subscription_ids = Options::get_post_meta( $course_id, 'subscription_ids', [] );

		if ( ! is_array( $subscription_ids ) || empty( $subscription_ids ) ) {
			return false;
		}

		foreach ( $subscription_ids as $subscription_id ) {
			if ( wcs_user_has_subscription( $user_id, (int) $subscription_id, 'active' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get product info for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_products_info( int $course_id ): array {
		if ( ! WooCommerce::is_active() ) {
			return [];
		}

		$product_ids = Options::get_post_meta( $course_id, 'product_ids', [] );

		if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
			return [];
		}

		$products = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );

			if ( ! $product ) {
				continue;
			}

			$products[] = [
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'price'           => $product->get_price(),
				'price_formatted' => $product->get_price_html(),
				'url'             => $product->get_permalink(),
			];
		}

		return $products;
	}

	/**
	 * Get subscription info for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_subscriptions_info( int $course_id ): array {
		if ( ! WooCommerce::is_active() ) {
			return [];
		}

		$subscription_ids = Options::get_post_meta( $course_id, 'subscription_ids', [] );

		if ( ! is_array( $subscription_ids ) || empty( $subscription_ids ) ) {
			return [];
		}

		$subscriptions = [];

		foreach ( $subscription_ids as $subscription_id ) {
			$product = wc_get_product( (int) $subscription_id );

			if ( ! $product ) {
				continue;
			}

			$subscriptions[] = [
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'price'           => $product->get_price(),
				'price_formatted' => $product->get_price_html(),
				'url'             => $product->get_permalink(),
			];
		}

		return $subscriptions;
	}
}
