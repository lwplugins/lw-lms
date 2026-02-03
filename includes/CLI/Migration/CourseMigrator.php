<?php
/**
 * Course Migrator for LearnDash migration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Migrates LearnDash courses to LW LMS courses.
 */
final class CourseMigrator {

	/**
	 * Logger.
	 *
	 * @var MigrationLogger
	 */
	private MigrationLogger $logger;

	/**
	 * Dry run flag.
	 *
	 * @var bool
	 */
	private bool $dry_run;

	/**
	 * Constructor.
	 *
	 * @param MigrationLogger $logger  Logger instance.
	 * @param bool            $dry_run Whether this is a dry run.
	 */
	public function __construct( MigrationLogger $logger, bool $dry_run ) {
		$this->logger  = $logger;
		$this->dry_run = $dry_run;
	}

	/**
	 * Migrate all LearnDash courses.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$courses = get_posts(
			[
				'post_type'      => 'sfwd-courses',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$this->logger->log( sprintf( 'Found %d LearnDash courses', count( $courses ) ) );

		foreach ( $courses as $course ) {
			$this->migrate_course( $course );
		}
	}

	/**
	 * Migrate a single course.
	 *
	 * @param \WP_Post $course LearnDash course post.
	 * @return void
	 */
	private function migrate_course( \WP_Post $course ): void {
		$this->logger->verbose( sprintf( '  Processing: %s (ID: %d)', $course->post_title, $course->ID ) );

		if ( $this->already_migrated( $course ) ) {
			return;
		}

		$settings    = LearnDashData::get_course_settings( $course->ID );
		$access_type = LearnDashData::map_access_type( $settings['sfwd-courses_course_price_type'] ?? 'open' );

		if ( $this->dry_run ) {
			$this->logger->log( sprintf( '    → Would create: %s (access: %s)', $course->post_title, $access_type ) );
			$this->logger->increment( 'courses_migrated' );
			return;
		}

		$new_id = PostCreator::create( $course, Course::POST_TYPE );

		if ( is_wp_error( $new_id ) ) {
			$this->logger->add_error( sprintf( 'Failed to create course %d: %s', $course->ID, $new_id->get_error_message() ) );
			return;
		}

		$this->set_course_meta( $course->ID, $new_id, $access_type );
		LearnDashData::store_mapping( $course->ID, $new_id );
		$this->logger->log( sprintf( '    → Created course ID: %d', $new_id ) );
		$this->logger->increment( 'courses_migrated' );
	}

	/**
	 * Check if course was already migrated.
	 *
	 * @param \WP_Post $course LearnDash course.
	 * @return bool
	 */
	private function already_migrated( \WP_Post $course ): bool {
		$existing = get_posts(
			[
				'post_type'      => Course::POST_TYPE,
				'title'          => $course->post_title,
				'posts_per_page' => 1,
			]
		);

		if ( ! empty( $existing ) ) {
			$this->logger->verbose( '    → Skipped: Already migrated' );
			$this->logger->increment( 'courses_skipped' );
			LearnDashData::store_mapping( $course->ID, $existing[0]->ID );
			return true;
		}

		return false;
	}

	/**
	 * Set meta for a newly created course.
	 *
	 * @param int    $source_id   LearnDash course ID.
	 * @param int    $new_id      New course ID.
	 * @param string $access_type Access type.
	 * @return void
	 */
	private function set_course_meta( int $source_id, int $new_id, string $access_type ): void {
		Options::set_post_meta( $new_id, 'access_type', $access_type );

		$product_ids = LearnDashData::get_woo_product_ids( $source_id );
		if ( ! empty( $product_ids ) ) {
			Options::set_post_meta( $new_id, 'product_ids', $product_ids );
		}

		PostCreator::copy_thumbnail( $source_id, $new_id );

		$duration = get_post_meta( $source_id, 'kurzus-hossza', true );
		if ( $duration ) {
			Options::set_post_meta( $new_id, 'duration', $duration );
		}
	}
}
