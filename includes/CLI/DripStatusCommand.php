<?php
/**
 * WP-CLI command: lw-lms drip status.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Drip\CoursePlan;
use LightweightPlugins\LMS\Drip\CourseStart;
use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Drip\DripTime;
use LightweightPlugins\LMS\Drip\LessonLocks;
use WP_CLI\Utils;

/**
 * Shows, lesson by lesson, what one learner can open in a course and why —
 * the answer to "why is this lesson still locked for them?".
 */
final class DripStatusCommand {

	/**
	 * Show a learner's drip state for a course.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms drip status alice 42
	 *
	 * @param array<int, string>    $args       Positional args. [0] = user, [1] = course.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( count( $args ) < 2 ) {
			\WP_CLI::error( 'Usage: wp lw-lms drip status <user> <course>' );
		}

		$user_id   = CliResolver::user_id( $args[0] );
		$course_id = CliResolver::course_id( $args[1] );
		$format    = (string) ( $assoc_args['format'] ?? 'table' );

		$locks = LessonLocks::for_course( $course_id, $user_id );
		$start = CourseStart::get( $user_id, $course_id );
		$plan  = CoursePlan::build( $course_id );

		if ( 'table' === $format ) {
			\WP_CLI::log(
				sprintf(
					'Course #%d: progression %s, course delay %s, clock starts %s.',
					$course_id,
					DripSettings::progression( $course_id ),
					DripArgs::describe( $plan['course_delay'] ),
					null === $start ? 'not started' : (string) DripTime::iso( $start )
				)
			);
		}

		$completed = CoursePlan::completed( $user_id, $course_id, $start ?? time() );
		$rows      = [];

		foreach ( $plan['sequence'] as $position => $item ) {
			$lock = $locks[ $item['id'] ] ?? null;

			$rows[] = [
				'position'     => $position + 1,
				'lesson_id'    => $item['id'],
				'title'        => get_the_title( $item['id'] ),
				'section'      => '' === $item['section'] ? '-' : $item['section'],
				'rule'         => DripArgs::describe( $plan['lesson_rules'][ $item['id'] ] ?? DripRule::none() ),
				'state'        => null === $lock ? 'open' : 'locked',
				'reason'       => $lock['reason'] ?? '',
				'available_at' => null === $lock ? '' : (string) DripTime::iso( $lock['available_at'] ),
				'completed_at' => isset( $completed[ $item['id'] ] ) ? (string) DripTime::iso( $completed[ $item['id'] ] ) : '',
			];
		}

		if ( ! $rows ) {
			\WP_CLI::warning( sprintf( 'Course #%d has no published lessons in its outline.', $course_id ) );
			return;
		}

		Utils\format_items(
			$format,
			$rows,
			[ 'position', 'lesson_id', 'title', 'section', 'rule', 'state', 'reason', 'available_at', 'completed_at' ]
		);
	}
}
