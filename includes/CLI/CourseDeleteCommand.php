<?php
/**
 * WP-CLI command: lw-lms course delete.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

/**
 * Delete a course.
 */
final class CourseDeleteCommand {

	/**
	 * Delete a course.
	 *
	 * ## OPTIONS
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * [--force]
	 * : Skip trash and permanently delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms course delete 42
	 *     wp lw-lms course delete my-course --force
	 *
	 * @param array<int, string>    $args       Positional args. [0] = course ref.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Course ID or slug is required.' );
		}

		$course_id = CliResolver::course_id( $args[0] );
		$force     = isset( $assoc_args['force'] );

		$result = wp_delete_post( $course_id, $force );

		if ( ! $result ) {
			\WP_CLI::error( sprintf( 'Failed to delete course #%d.', $course_id ) );
		}

		\WP_CLI::success(
			sprintf(
				'Course #%d %s.',
				$course_id,
				$force ? 'permanently deleted' : 'moved to trash'
			)
		);
	}
}
