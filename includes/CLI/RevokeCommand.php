<?php
/**
 * WP-CLI command: lw-lms revoke.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Access\AccessRepository;

/**
 * Revoke a user's access to a course.
 */
final class RevokeCommand {

	/**
	 * Revoke a user's access to a course.
	 *
	 * Flips the active access row to status='revoked'. Fires
	 * lw_lms_after_revoke only if a row was actually flipped.
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
	 *     wp lw-lms revoke alice 42
	 *
	 * @param array<int, string>    $args       Positional args. [0] = user, [1] = course.
	 * @param array<string, string> $assoc_args Associative args (unused).
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI signature.
		if ( count( $args ) < 2 ) {
			\WP_CLI::error( 'Usage: wp lw-lms revoke <user> <course>' );
		}

		$user_id   = CliResolver::user_id( $args[0] );
		$course_id = CliResolver::course_id( $args[1] );

		$result = AccessRepository::revoke( $user_id, $course_id );

		if ( ! $result ) {
			\WP_CLI::warning(
				sprintf( 'No active access row to revoke for user #%d / course #%d.', $user_id, $course_id )
			);
			return;
		}

		\WP_CLI::success( sprintf( 'Revoked access for user #%d on course #%d.', $user_id, $course_id ) );
	}
}
