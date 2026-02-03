<?php
/**
 * Section Builder for LearnDash migration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

use LightweightPlugins\LMS\Options;

/**
 * Creates course sections and sets lesson ordering.
 */
final class SectionBuilder {

	/**
	 * Logger instance.
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
	 * Build sections and ordering for all migrated courses.
	 *
	 * @return void
	 */
	public function build(): void {
		$courses = get_posts(
			[
				'post_type'      => 'sfwd-courses',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			]
		);

		foreach ( $courses as $ld_course ) {
			$this->build_for_course( $ld_course );
		}
	}

	/**
	 * Build sections and lesson order for a single course.
	 *
	 * @param \WP_Post $ld_course LearnDash course.
	 * @return void
	 */
	private function build_for_course( \WP_Post $ld_course ): void {
		$new_course_id = LearnDashData::get_migrated_id( $ld_course->ID );

		if ( ! $new_course_id ) {
			return;
		}

		$lesson_ids = LearnDashData::get_lesson_order( $ld_course->ID );

		if ( empty( $lesson_ids ) ) {
			return;
		}

		$this->logger->verbose( sprintf( '  Course %d has %d lessons to order', $new_course_id, count( $lesson_ids ) ) );

		$section_id = 'section-' . wp_generate_uuid4();
		$section    = [
			'id'          => $section_id,
			'title'       => 'Leckék',
			'description' => '',
			'order'       => 0,
		];

		if ( ! $this->dry_run ) {
			Options::set_post_meta( $new_course_id, 'course_sections', [ $section ] );
		}

		$order = 0;
		foreach ( $lesson_ids as $ld_lesson_id ) {
			$new_lesson_id = LearnDashData::get_migrated_id( (int) $ld_lesson_id );

			if ( ! $new_lesson_id ) {
				continue;
			}

			if ( ! $this->dry_run ) {
				Options::set_post_meta( $new_lesson_id, 'lesson_section_id', $section_id );
				Options::set_post_meta( $new_lesson_id, 'lesson_order', $order );
			}

			++$order;
		}

		$this->logger->verbose( sprintf( '    → Updated order for %d lessons', $order ) );
	}
}
