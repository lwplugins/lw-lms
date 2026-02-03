<?php
/**
 * Lesson Migrator for LearnDash migration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Migrates LearnDash lessons to LW LMS lessons.
 */
final class LessonMigrator {

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
	 * Migrate all LearnDash lessons.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$lessons = get_posts(
			[
				'post_type'      => 'sfwd-lessons',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$this->logger->log( sprintf( 'Found %d LearnDash lessons', count( $lessons ) ) );

		foreach ( $lessons as $lesson ) {
			$this->migrate_lesson( $lesson );
		}
	}

	/**
	 * Migrate a single lesson.
	 *
	 * @param \WP_Post $lesson LearnDash lesson post.
	 * @return void
	 */
	private function migrate_lesson( \WP_Post $lesson ): void {
		$this->logger->verbose( sprintf( '  Processing: %s (ID: %d)', $lesson->post_title, $lesson->ID ) );

		$ld_course_id  = (int) get_post_meta( $lesson->ID, 'course_id', true );
		$new_course_id = $ld_course_id ? LearnDashData::get_migrated_id( $ld_course_id ) : 0;

		if ( $this->already_migrated( $lesson, $new_course_id ) ) {
			return;
		}

		$video_data = VideoExtractor::extract( $lesson, LearnDashData::get_lesson_settings( $lesson->ID ) );

		if ( $this->dry_run ) {
			$this->logger->log( sprintf( '    → Would create: %s (course: %d)', $lesson->post_title, $new_course_id ) );
			if ( ! empty( $video_data['url'] ) ) {
				$this->logger->verbose( sprintf( '      Video: %s (%s)', $video_data['url'], $video_data['provider'] ) );
			}
			$this->logger->increment( 'lessons_migrated' );
			return;
		}

		$new_id = PostCreator::create( $lesson, Lesson::POST_TYPE );

		if ( is_wp_error( $new_id ) ) {
			$this->logger->add_error( sprintf( 'Failed to create lesson %d: %s', $lesson->ID, $new_id->get_error_message() ) );
			return;
		}

		if ( $new_course_id ) {
			Options::set_post_meta( $new_id, 'lesson_course_id', $new_course_id );
		}
		if ( ! empty( $video_data['url'] ) ) {
			Options::set_post_meta( $new_id, 'video', $video_data );
		}
		PostCreator::copy_thumbnail( $lesson->ID, $new_id );

		LearnDashData::store_mapping( $lesson->ID, $new_id );
		$this->logger->log( sprintf( '    → Created lesson ID: %d', $new_id ) );
		$this->logger->increment( 'lessons_migrated' );
	}

	/**
	 * Check if lesson was already migrated.
	 *
	 * @param \WP_Post $lesson        LearnDash lesson.
	 * @param int      $new_course_id New course ID.
	 * @return bool
	 */
	private function already_migrated( \WP_Post $lesson, int $new_course_id ): bool {
		$existing = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'title'          => $lesson->post_title,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration duplicate check.
					[
						'key'   => Options::META_PREFIX . 'lesson_course_id',
						'value' => $new_course_id,
					],
				],
				'posts_per_page' => 1,
			]
		);

		if ( ! empty( $existing ) ) {
			$this->logger->verbose( '    → Skipped: Already migrated' );
			$this->logger->increment( 'lessons_skipped' );
			LearnDashData::store_mapping( $lesson->ID, $existing[0]->ID );
			return true;
		}

		return false;
	}
}
