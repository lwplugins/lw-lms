<?php
/**
 * Access Checker.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;

/**
 * Handles access control for courses and lessons.
 */
final class AccessChecker {

	/**
	 * Access type constants.
	 */
	public const ACCESS_OPEN = 'open';
	public const ACCESS_FREE = 'free';
	public const ACCESS_PAID = 'paid';

	/**
	 * Check if a user has access to a course.
	 *
	 * @param int      $course_id Course ID.
	 * @param int|null $user_id   User ID (null = current user).
	 * @return bool
	 */
	public static function has_course_access( int $course_id, ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$access_type = Options::get_post_meta( $course_id, 'access_type', self::ACCESS_FREE );

		// Open courses are accessible to everyone.
		if ( self::ACCESS_OPEN === $access_type ) {
			return true;
		}

		// User must be logged in for free and paid courses.
		if ( ! $user_id ) {
			return false;
		}

		// Free courses are accessible to logged-in users.
		if ( self::ACCESS_FREE === $access_type ) {
			return true;
		}

		// Paid courses: check access table, subscriptions, then legacy fallback.
		if ( self::ACCESS_PAID === $access_type ) {
			// 1. Access table check.
			if ( AccessRepository::has_active_access( $user_id, $course_id ) ) {
				return true;
			}

			// 2. Parent-level subscription check.
			if ( WooCommerceChecker::has_active_subscription( $course_id, $user_id ) ) {
				return true;
			}

			// 3. Variation-level subscription check (specific variations of variable subscriptions).
			if ( SubscriptionVariationChecker::has_active( $course_id, $user_id ) ) {
				return true;
			}

			// 4. Fallback: legacy purchases without durations.
			return WooCommerceChecker::has_legacy_purchase( $course_id, $user_id );
		}

		/**
		 * Filter whether user has access to a course.
		 *
		 * @param bool $has_access Whether user has access.
		 * @param int  $course_id  Course ID.
		 * @param int  $user_id    User ID.
		 */
		return apply_filters( 'lw_lms_has_course_access', false, $course_id, $user_id );
	}

	/**
	 * Check if a user has access to a lesson.
	 *
	 * @param int      $lesson_id Lesson ID.
	 * @param int|null $user_id   User ID (null = current user).
	 * @return bool
	 */
	public static function has_lesson_access( int $lesson_id, ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		if ( ! $course_id ) {
			return false;
		}

		// Check if lesson is a preview lesson.
		if ( self::is_preview_lesson( $lesson_id, $course_id ) ) {
			// Preview lessons require login.
			return $user_id > 0;
		}

		// Otherwise, check course access.
		$has_access = self::has_course_access( $course_id, $user_id );

		/**
		 * Filter whether user has access to a lesson.
		 *
		 * @param bool $has_access Whether user has access.
		 * @param int  $lesson_id  Lesson ID.
		 * @param int  $user_id    User ID.
		 */
		return apply_filters( 'lw_lms_has_lesson_access', $has_access, $lesson_id, $user_id );
	}

	/**
	 * Check if a lesson is a preview lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_preview_lesson( int $lesson_id, int $course_id ): bool {
		$preview_lessons = Options::get_post_meta( $course_id, 'preview_lesson_ids', [] );

		if ( ! is_array( $preview_lessons ) ) {
			return false;
		}

		return in_array( $lesson_id, array_map( 'intval', $preview_lessons ), true );
	}

	/**
	 * Get access type for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function get_access_type( int $course_id ): string {
		return Options::get_post_meta( $course_id, 'access_type', self::ACCESS_FREE );
	}

	/**
	 * Get access info for a course.
	 *
	 * @param int      $course_id Course ID.
	 * @param int|null $user_id   User ID.
	 * @return array
	 */
	public static function get_access_info( int $course_id, ?int $user_id = null ): array {
		$access_type = self::get_access_type( $course_id );
		$has_access  = self::has_course_access( $course_id, $user_id );

		$info = [
			'type'       => $access_type,
			'has_access' => $has_access,
		];

		// Add expiry info for users with access.
		if ( $has_access && self::ACCESS_PAID === $access_type ) {
			$user_id = $user_id ? $user_id : get_current_user_id();

			if ( $user_id ) {
				$access_record = AccessRepository::get_user_access( $user_id, $course_id );

				if ( $access_record && $access_record->expires_at ) {
					$info['expires_at'] = $access_record->expires_at;
				}
			}
		}

		// Add purchase info for paid courses without access.
		if ( self::ACCESS_PAID === $access_type && ! $has_access ) {
			$info['requires']                = 'purchase';
			$info['products']                = WooCommerceChecker::get_products_info( $course_id );
			$info['subscriptions']           = WooCommerceChecker::get_subscriptions_info( $course_id );
			$info['subscription_variations'] = SubscriptionVariationChecker::get_info( $course_id );
		}

		return $info;
	}
}
