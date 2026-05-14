<?php
/**
 * WP-CLI command: lw-lms force-complete.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Progress\ProgressRepository;

/**
 * Mark every lesson of a course as completed for a user.
 */
final class ForceCompleteCommand {

	/**
	 * Force-complete a course for a user.
	 *
	 * Upserts every published lesson assigned to the course as completed.
	 * The final upsert triggers lw_lms_course_completed via CompletionTracker.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms force-complete alice 42
	 *
	 * @param array<int, string>    $args       Positional args. [0] = user, [1] = course.
	 * @param array<string, string> $assoc_args Associative args (unused).
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI signature.
		if ( count( $args ) < 2 ) {
			\WP_CLI::error( 'Usage: wp lw-lms force-complete <user> <course>' );
		}

		$user_id   = CliResolver::user_id( $args[0] );
		$course_id = CliResolver::course_id( $args[1] );

		$result = ProgressRepository::mark_course_completed( $user_id, $course_id );

		if ( ! $result ) {
			\WP_CLI::warning(
				sprintf( 'No published lessons found for course #%d — nothing to complete.', $course_id )
			);
			return;
		}

		\WP_CLI::success( sprintf( 'Force-completed course #%d for user #%d.', $course_id, $user_id ) );
	}
}
