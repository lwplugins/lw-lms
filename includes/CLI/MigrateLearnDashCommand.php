<?php
/**
 * WP-CLI command to migrate from LearnDash.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\CLI\Migration\MigrationLogger;
use LightweightPlugins\LMS\CLI\Migration\CourseMigrator;
use LightweightPlugins\LMS\CLI\Migration\LessonMigrator;
use LightweightPlugins\LMS\CLI\Migration\SectionBuilder;

/**
 * Migrate courses and lessons from LearnDash to LW LMS.
 */
final class MigrateLearnDashCommand {

	/**
	 * Migrate all courses and lessons from LearnDash to LW LMS.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview changes without making them.
	 *
	 * [--verbose]
	 * : Show detailed output for each item.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration
	 *     wp lw-lms migrate-learndash --dry-run
	 *
	 *     # Run migration with verbose output
	 *     wp lw-lms migrate-learndash --verbose
	 *
	 *     # Run migration
	 *     wp lw-lms migrate-learndash
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI command signature.
		$dry_run = isset( $assoc_args['dry-run'] );
		$verbose = isset( $assoc_args['verbose'] );
		$logger  = new MigrationLogger( $verbose );

		$logger->log( '========================================' );
		$logger->log( 'LearnDash to LW LMS Migration' );
		$logger->log( '========================================' );

		if ( $dry_run ) {
			$logger->warning( 'DRY RUN MODE - No changes will be made' );
		}

		if ( ! $this->has_learndash_data() ) {
			$logger->warning( 'No LearnDash data found. Nothing to migrate.' );
			return;
		}

		$logger->log( '' );
		$logger->log( '[Step 1/3] Migrating courses...' );
		$course_migrator = new CourseMigrator( $logger, $dry_run );
		$course_migrator->migrate();

		$logger->log( '' );
		$logger->log( '[Step 2/3] Migrating lessons...' );
		$lesson_migrator = new LessonMigrator( $logger, $dry_run );
		$lesson_migrator->migrate();

		$logger->log( '' );
		$logger->log( '[Step 3/3] Updating course sections and lesson order...' );
		$section_builder = new SectionBuilder( $logger, $dry_run );
		$section_builder->build();

		$logger->print_summary( $dry_run );

		if ( ! $dry_run ) {
			$logger->success( 'Migration completed.' );
		}
	}

	/**
	 * Check if LearnDash data exists.
	 *
	 * @return bool
	 */
	private function has_learndash_data(): bool {
		$courses = get_posts(
			[
				'post_type'      => 'sfwd-courses',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		return ! empty( $courses );
	}
}
