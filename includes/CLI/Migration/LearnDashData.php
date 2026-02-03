<?php
/**
 * LearnDash Data Helpers.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

/**
 * Provides helper methods for reading LearnDash data.
 */
final class LearnDashData {

	/**
	 * Get LearnDash course settings.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_course_settings( int $course_id ): array {
		$settings = get_post_meta( $course_id, '_sfwd-courses', true );

		if ( is_string( $settings ) ) {
			$settings = maybe_unserialize( $settings );
		}

		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get LearnDash lesson settings.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array
	 */
	public static function get_lesson_settings( int $lesson_id ): array {
		$settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );

		if ( is_string( $settings ) ) {
			$settings = maybe_unserialize( $settings );
		}

		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Map LearnDash price type to LW LMS access type.
	 *
	 * @param string $ld_price_type LearnDash price type.
	 * @return string
	 */
	public static function map_access_type( string $ld_price_type ): string {
		return match ( $ld_price_type ) {
			'open' => 'open',
			'free' => 'free',
			'paynow', 'subscribe', 'closed' => 'paid',
			default => 'free',
		};
	}

	/**
	 * Get WooCommerce product IDs linked to a course.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return array
	 */
	public static function get_woo_product_ids( int $course_id ): array {
		global $wpdb;

		$product_ids = [];

		$direct = get_post_meta( $course_id, '_related_course', true );
		if ( $direct ) {
			$product_ids[] = (int) $direct;
		}

		$membership = get_post_meta( $course_id, '_membership_product_id', true );
		if ( $membership ) {
			$product_ids[] = (int) $membership;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration query, runs once.
		$products = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_related_course' AND meta_value = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				$course_id
			)
		);

		$product_ids = array_merge( $product_ids, array_map( 'intval', $products ) );

		return array_unique( array_filter( $product_ids ) );
	}

	/**
	 * Get course steps (lesson order) from LearnDash.
	 *
	 * @param int $course_id LearnDash course ID.
	 * @return array Ordered lesson IDs.
	 */
	public static function get_lesson_order( int $course_id ): array {
		$steps = get_post_meta( $course_id, 'ld_course_steps', true );

		if ( is_string( $steps ) ) {
			$steps = maybe_unserialize( $steps );
		}

		if ( ! is_array( $steps ) || empty( $steps['steps']['h']['sfwd-lessons'] ) ) {
			return [];
		}

		return array_keys( $steps['steps']['h']['sfwd-lessons'] );
	}

	/**
	 * Get the migrated post ID for a LearnDash post.
	 *
	 * @param int $ld_post_id LearnDash post ID.
	 * @return int New post ID or 0.
	 */
	public static function get_migrated_id( int $ld_post_id ): int {
		return (int) get_post_meta( $ld_post_id, '_lw_lms_migrated_to', true );
	}

	/**
	 * Store migration mapping.
	 *
	 * @param int $ld_post_id  LearnDash post ID.
	 * @param int $new_post_id New LW LMS post ID.
	 * @return void
	 */
	public static function store_mapping( int $ld_post_id, int $new_post_id ): void {
		update_post_meta( $ld_post_id, '_lw_lms_migrated_to', $new_post_id );
	}
}
