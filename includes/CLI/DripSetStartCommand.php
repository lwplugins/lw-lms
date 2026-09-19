<?php
/**
 * WP-CLI command: lw-lms drip set-start.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Drip\CourseStart;
use LightweightPlugins\LMS\Drip\DripTime;

/**
 * Moves (or clears) the moment a learner's drip clock started.
 */
final class DripSetStartCommand {

	/**
	 * Set the start of a learner's course clock.
	 *
	 * Every "after enrollment" delay is measured from this moment. Clearing
	 * it makes the next access start a fresh clock.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * [--date=<datetime>]
	 * : Site-local datetime, e.g. "2026-09-01 08:00:00". Defaults to now.
	 *
	 * [--clear]
	 * : Forget the stored clock instead of setting one.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms drip set-start alice 42 --date="2026-09-01 08:00:00"
	 *     wp lw-lms drip set-start alice 42 --clear
	 *
	 * @param array<int, string>    $args       Positional args. [0] = user, [1] = course.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( count( $args ) < 2 ) {
			\WP_CLI::error( 'Usage: wp lw-lms drip set-start <user> <course> [--date=<datetime>] [--clear]' );
		}

		$user_id   = CliResolver::user_id( $args[0] );
		$course_id = CliResolver::course_id( $args[1] );

		if ( isset( $assoc_args['clear'] ) ) {
			CourseStart::clear( $user_id, $course_id );
			\WP_CLI::success( sprintf( 'Cleared the course clock of user #%d on course #%d.', $user_id, $course_id ) );
			return;
		}

		$date      = (string) ( $assoc_args['date'] ?? '' );
		$timestamp = '' === $date ? time() : DripTime::from_mysql( $date );

		if ( null === $timestamp ) {
			\WP_CLI::error( sprintf( 'Could not read "%s" as a datetime.', $date ) );
		}

		CourseStart::set( $user_id, $course_id, $timestamp );

		\WP_CLI::success(
			sprintf(
				'User #%d starts course #%d at %s.',
				$user_id,
				$course_id,
				(string) DripTime::iso( $timestamp )
			)
		);
	}
}
