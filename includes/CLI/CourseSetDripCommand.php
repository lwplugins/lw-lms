<?php
/**
 * WP-CLI command: lw-lms course set-drip.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Options;

/**
 * Sets a course's progression mode and the delay before it opens.
 */
final class CourseSetDripCommand {

	/**
	 * Configure progression and drip on a course.
	 *
	 * ## OPTIONS
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * [--progression=<mode>]
	 * : free (any lesson, any order) or linear (one after the other, drip applies).
	 * ---
	 * options:
	 *   - free
	 *   - linear
	 * ---
	 *
	 * [--delay=<number>]
	 * : How long after enrollment the course opens. 0 opens it immediately.
	 *
	 * [--unit=<unit>]
	 * : Unit of the delay.
	 * ---
	 * default: day
	 * options:
	 *   - hour
	 *   - day
	 *   - week
	 *   - month
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms course set-drip 42 --progression=linear
	 *     wp lw-lms course set-drip 42 --delay=2 --unit=week
	 *
	 * @param array<int, string>    $args       Positional args. [0] = course.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Course ID or slug is required.' );
		}

		$course_id = CliResolver::course_id( $args[0] );

		if ( isset( $assoc_args['progression'] ) ) {
			$progression = (string) $assoc_args['progression'];

			if ( ! in_array( $progression, [ DripSettings::PROGRESSION_FREE, DripSettings::PROGRESSION_LINEAR ], true ) ) {
				\WP_CLI::error( 'Progression must be "free" or "linear".' );
			}

			Options::set_post_meta( $course_id, DripSettings::META_PROGRESSION, $progression );
		}

		if ( isset( $assoc_args['delay'] ) ) {
			$rule = DripArgs::rule(
				DripRule::MODE_ENROLLMENT,
				(string) $assoc_args['delay'],
				(string) ( $assoc_args['unit'] ?? DripRule::DEFAULT_UNIT ),
				[ DripRule::MODE_ENROLLMENT ]
			);

			Options::set_post_meta(
				$course_id,
				DripSettings::META_COURSE_DELAY,
				$rule['value'] > 0 ? $rule : DripRule::none()
			);
		}

		\WP_CLI::success(
			sprintf(
				'Course #%d: progression %s, opens %s.',
				$course_id,
				DripSettings::progression( $course_id ),
				DripArgs::describe( DripSettings::course_delay( $course_id ) )
			)
		);
	}
}
