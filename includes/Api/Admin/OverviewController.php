<?php
/**
 * Overview REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Access\EnrollmentList;
use LightweightPlugins\LMS\Access\MembershipChecker;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Quiz\QuizAttemptSearch;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET lw-lms/v1/admin/overview: the numbers on the Overview screen. The
 * WooCommerce block is only present while WooCommerce is active.
 */
final class OverviewController {

	/**
	 * Days the quiz attempt count looks back.
	 */
	public const ATTEMPT_DAYS = 30;

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		AdminRoutes::add(
			'/admin/overview',
			[ WP_REST_Server::READABLE => 'get_overview' ],
			$this,
			[ AdminRoutes::class, 'can_open_screen' ]
		);
	}

	/**
	 * Counts and integration state.
	 *
	 * @return WP_REST_Response
	 */
	public function get_overview(): WP_REST_Response {
		// submitted_at is site-local, so the cut-off is in site time too.
		$since = (string) wp_date( 'Y-m-d H:i:s', time() - self::ATTEMPT_DAYS * DAY_IN_SECONDS );

		$data = [
			'courses'         => self::post_counts( Course::POST_TYPE ),
			'lessons'         => self::post_counts( Lesson::POST_TYPE ),
			'enrollments'     => EnrollmentList::count_active(),
			'quizAttempts'    => QuizAttemptSearch::count_since( $since ),
			'quizAttemptDays' => self::ATTEMPT_DAYS,
			'woocommerce'     => null,
		];

		if ( WooCommerce::is_active() ) {
			$data['woocommerce'] = [
				'enabled'       => (bool) Options::get( 'woo_enabled', true ),
				'subscriptions' => WooCommerce::is_subscriptions_active(),
				'memberships'   => MembershipChecker::is_active(),
				'paidCourses'   => self::paid_courses(),
				'orderAccess'   => EnrollmentList::count(
					[
						'source' => 'woocommerce',
						'status' => 'active',
					]
				),
			];
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Published and unpublished posts of a type.
	 *
	 * @param string $post_type Post type.
	 * @return array{published: int, drafts: int}
	 */
	private static function post_counts( string $post_type ): array {
		$counts = wp_count_posts( $post_type );

		return [
			'published' => (int) ( $counts->publish ?? 0 ),
			'drafts'    => (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->future ?? 0 ),
		];
	}

	/**
	 * Published courses sold through WooCommerce (access type "paid").
	 *
	 * @return int
	 */
	private static function paid_courses(): int {
		$ids = get_posts(
			[
				'post_type'      => Course::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => Options::META_PREFIX . 'access_type',
				'meta_value'     => 'paid',
			]
		);

		return count( $ids );
	}
}
