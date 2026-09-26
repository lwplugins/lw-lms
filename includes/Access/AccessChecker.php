<?php
/**
 * Access Checker.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Api\StatusPermission;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

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

		// Fail closed: a missing course, a non-course post or a course the
		// user may not read in its current status (draft, private, trash)
		// grants nothing, whatever its access type says.
		if ( ! self::is_readable( $course_id, Course::POST_TYPE, $user_id ) ) {
			return false;
		}

		$access_type = Options::get_post_meta( $course_id, 'access_type', self::ACCESS_FREE );

		// Open courses are accessible to everyone.
		if ( self::ACCESS_OPEN === $access_type ) {
			return true;
		}

		// Staff bypass (opt-in setting). Runtime-only: no access row, no
		// lw_lms_after_grant, and no lazy free-course grant for staff.
		if ( AdminAccess::applies( $user_id ) ) {
			return true;
		}

		// User must be logged in for free and paid courses.
		if ( ! $user_id ) {
			return false;
		}

		// Free courses are accessible to logged-in users.
		if ( self::ACCESS_FREE === $access_type ) {
			// Implicit enrollment: lazily insert a source='free' row on first
			// access so callers get an lw_lms_after_grant event for free
			// courses (drip / welcome email / cohort analytics).
			if ( ! AccessQueries::has_active_access( $user_id, $course_id, 'free' ) ) {
				AccessRepository::grant( $user_id, $course_id, 'free', null, null );
			}

			return true;
		}

		// Paid courses: check access table, subscriptions, memberships, then the
		// legacy fallback. Short-circuits on the first grant so later (heavier)
		// checks are skipped, exactly like the previous early-return chain.
		$has_access = false;

		if ( self::ACCESS_PAID === $access_type ) {
			$has_access = AccessQueries::has_active_access( $user_id, $course_id )                 // 1. Access table.
				|| WooCommerceChecker::has_active_subscription( $course_id, $user_id )             // 2. Parent subscription.
				|| SubscriptionVariationChecker::has_active( $course_id, $user_id )                // 3. Variation subscription.
				|| MembershipChecker::has_active( $course_id, $user_id )                           // 4. WC Membership.
				|| WooCommerceChecker::has_legacy_purchase( $course_id, $user_id );                // 5. Legacy purchase.
		}

		/**
		 * Filter whether a user has access to a course.
		 *
		 * Runs AFTER the built-in checks (including the paid branch), so an
		 * integration can grant — or revoke — access as the final say. Previously
		 * the paid branch returned before this line, making the filter dead for
		 * paid courses (the common extension point).
		 *
		 * @param bool $has_access Whether the built-in checks granted access.
		 * @param int  $course_id  Course ID.
		 * @param int  $user_id    User ID.
		 */
		return (bool) apply_filters( 'lw_lms_has_course_access', $has_access, $course_id, $user_id );
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

		// The lesson itself must be readable in its status: a draft lesson is
		// not opened (or completed) by guessing its ID.
		if ( ! self::is_readable( $lesson_id, Lesson::POST_TYPE, $user_id ) ) {
			return false;
		}

		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		// Fail closed when the parent course is gone, is not a course, or is
		// not readable (draft/private): its lessons must not fall back to the
		// default "free" access type.
		if ( ! $course_id || ! self::is_readable( $course_id, Course::POST_TYPE, $user_id ) ) {
			return false;
		}

		// Open courses are accessible to everyone — the preview flag is moot
		// because the entire course is already public, so checking it first
		// would wrongly require login for an open-course lesson marked preview.
		if ( self::ACCESS_OPEN === self::get_access_type( $course_id ) ) {
			$has_access = true;

			/** This filter is documented below. */
			return apply_filters( 'lw_lms_has_lesson_access', $has_access, $lesson_id, $user_id );
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
	 * Whether a post exists, has the expected type and is readable by the user.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Expected post type.
	 * @param int    $user_id   User ID (0 = guest).
	 * @return bool
	 */
	private static function is_readable( int $post_id, string $post_type, int $user_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post
			&& $post_type === $post->post_type
			&& StatusPermission::can_read_post( $post, $user_id );
	}

	/**
	 * Check if a lesson is a preview lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_preview_lesson( int $lesson_id, int $course_id ): bool {
		return in_array( $lesson_id, PreviewLessons::active( $course_id ), true );
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
				$access_record = AccessQueries::get_user_access( $user_id, $course_id );

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
			$info['memberships']             = MembershipChecker::get_info( $course_id );
		}

		return $info;
	}
}
