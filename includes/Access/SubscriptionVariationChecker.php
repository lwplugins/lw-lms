<?php
/**
 * Subscription Variation Checker.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Checks variation-level WooCommerce Subscriptions access.
 *
 * Complements WooCommerceChecker::has_active_subscription() which only
 * matches the parent subscription product. This class matches against a
 * specific (parent_id, variation_id) pair so a course can be tied to one
 * variation of a variable-subscription product (e.g. only the "Yearly"
 * variation grants access, not "Monthly").
 */
final class SubscriptionVariationChecker {

	/**
	 * Check if the user has an active subscription on any of the configured
	 * parent:variation pairs for the course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function has_active( int $course_id, int $user_id ): bool {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return false;
		}

		$allowed = self::get_allowed_pairs( $course_id );

		if ( empty( $allowed ) ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( ! $subscription->has_status( 'active' ) ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				$variation_id = (int) $item->get_variation_id();

				if ( $variation_id <= 0 ) {
					continue;
				}

				$pair = $item->get_product_id() . ':' . $variation_id;

				if ( in_array( $pair, $allowed, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get info about the variation-level subscriptions configured for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_info( int $course_id ): array {
		if ( ! WooCommerce::is_active() ) {
			return [];
		}

		$pairs = self::get_allowed_pairs( $course_id );

		if ( empty( $pairs ) ) {
			return [];
		}

		$info = [];

		foreach ( $pairs as $pair ) {
			$parts = explode( ':', $pair, 2 );

			if ( count( $parts ) !== 2 ) {
				continue;
			}

			$parent_id    = (int) $parts[0];
			$variation_id = (int) $parts[1];

			$variation = wc_get_product( $variation_id );

			if ( ! $variation ) {
				continue;
			}

			$info[] = [
				'parent_id'       => $parent_id,
				'variation_id'    => $variation_id,
				'name'            => $variation->get_name(),
				'attributes'      => is_callable( [ $variation, 'get_variation_attributes' ] ) ? $variation->get_variation_attributes() : [],
				'price'           => $variation->get_price(),
				'price_formatted' => $variation->get_price_html(),
				'url'             => $variation->get_permalink(),
			];
		}

		return $info;
	}

	/**
	 * Read and normalize the configured parent:variation pairs for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array<int, string>
	 */
	private static function get_allowed_pairs( int $course_id ): array {
		$raw = Options::get_post_meta( $course_id, 'subscription_variation_ids', [] );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}

			if ( ! preg_match( '/^\d+:\d+$/', $entry ) ) {
				continue;
			}

			$out[] = $entry;
		}

		return $out;
	}
}
