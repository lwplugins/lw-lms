<?php
/**
 * WP-CLI command: lw-lms enroll.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Access\AccessRepository;

/**
 * Grant a user access to a course.
 */
final class EnrollCommand {

	/**
	 * Grant access (enroll) a user to a course.
	 *
	 * Idempotent: re-running on an existing access row updates the grant
	 * timestamp and expires_at.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * [--source=<source>]
	 * : Access source label stored on the row.
	 * ---
	 * default: manual
	 * ---
	 *
	 * [--expires-at=<datetime>]
	 * : Expiration datetime (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Omit for unlimited.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms enroll alice 42
	 *     wp lw-lms enroll alice@example.com 42 --expires-at=2027-01-01
	 *     wp lw-lms enroll 7 my-course --source=manual
	 *
	 * @param array<int, string>    $args       Positional args. [0] = user, [1] = course.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( count( $args ) < 2 ) {
			\WP_CLI::error( 'Usage: wp lw-lms enroll <user> <course>' );
		}

		$user_id   = CliResolver::user_id( $args[0] );
		$course_id = CliResolver::course_id( $args[1] );
		$source    = (string) ( $assoc_args['source'] ?? 'manual' );

		$expires_at = null;
		if ( ! empty( $assoc_args['expires-at'] ) ) {
			$timestamp = strtotime( (string) $assoc_args['expires-at'] );
			if ( false === $timestamp ) {
				\WP_CLI::error( sprintf( 'Invalid --expires-at: %s', $assoc_args['expires-at'] ) );
			}
			$expires_at = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		$result = AccessRepository::grant( $user_id, $course_id, $source, null, $expires_at );

		if ( ! $result ) {
			\WP_CLI::error(
				sprintf(
					'Grant failed for user #%d / course #%d (filter may have aborted it).',
					$user_id,
					$course_id
				)
			);
		}

		\WP_CLI::success(
			sprintf(
				'Enrolled user #%d to course #%d (source: %s%s).',
				$user_id,
				$course_id,
				$source,
				$expires_at ? ', expires: ' . $expires_at : ''
			)
		);
	}
}
