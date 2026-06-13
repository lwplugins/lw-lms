<?php
/**
 * WooCommerce Memberships Access Checker.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Checks active WooCommerce Memberships for course access.
 */
final class MembershipChecker {

	/**
	 * Whether WooCommerce Memberships is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return function_exists( 'wc_memberships_is_user_active_member' );
	}

	/**
	 * Check if a user is an active member of any plan linked to the course.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public static function has_active( int $course_id, int $user_id ): bool {
		if ( ! self::is_active() || $user_id <= 0 ) {
			return false;
		}

		$plan_ids = self::get_plan_ids( $course_id );

		if ( empty( $plan_ids ) ) {
			return false;
		}

		foreach ( $plan_ids as $plan_id ) {
			if ( wc_memberships_is_user_active_member( $user_id, $plan_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get membership info for a course (for the denied-access payload).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_info( int $course_id ): array {
		if ( ! self::is_active() || ! function_exists( 'wc_memberships_get_membership_plan' ) || ! WooCommerce::is_active() ) {
			return [];
		}

		$plan_ids = self::get_plan_ids( $course_id );
		$plans    = [];

		foreach ( $plan_ids as $plan_id ) {
			$plan = wc_memberships_get_membership_plan( $plan_id );

			if ( ! $plan ) {
				continue;
			}

			$plans[] = [
				'id'   => $plan->get_id(),
				'name' => $plan->get_name(),
				'join' => self::get_join_url( $plan ),
			];
		}

		return $plans;
	}

	/**
	 * Get the configured plan IDs for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private static function get_plan_ids( int $course_id ): array {
		$plan_ids = Options::get_post_meta( $course_id, 'membership_plan_ids', [] );

		if ( ! is_array( $plan_ids ) ) {
			return [];
		}

		return array_map( 'intval', $plan_ids );
	}

	/**
	 * Resolve a "join" URL for a plan: first product permalink, else plan permalink.
	 *
	 * @param \WC_Memberships_Membership_Plan $plan Plan object.
	 * @return string
	 */
	private static function get_join_url( $plan ): string {
		$product_ids = $plan->get_product_ids();

		if ( is_array( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( (int) $product_id );

				if ( $product ) {
					return (string) $product->get_permalink();
				}
			}
		}

		return (string) get_permalink( $plan->get_id() );
	}
}
