<?php
/**
 * WP-CLI command: lw-lms lesson set-drip.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Options;

/**
 * Sets when a single lesson opens.
 */
final class LessonSetDripCommand {

	/**
	 * Configure the drip rule of a lesson.
	 *
	 * ## OPTIONS
	 *
	 * <lesson>
	 * : Lesson ID or slug.
	 *
	 * --mode=<mode>
	 * : none (opens right away), enrollment (so long after the learner got the
	 * course) or previous (so long after the previous lesson is completed).
	 * ---
	 * options:
	 *   - none
	 *   - enrollment
	 *   - previous
	 * ---
	 *
	 * [--delay=<number>]
	 * : Length of the delay. Defaults to 0.
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
	 *     wp lw-lms lesson set-drip 108 --mode=previous --delay=3 --unit=day
	 *     wp lw-lms lesson set-drip 108 --mode=none
	 *
	 * @param array<int, string>    $args       Positional args. [0] = lesson.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Lesson ID or slug is required.' );
		}

		if ( ! isset( $assoc_args['mode'] ) ) {
			\WP_CLI::error( '--mode is required (none, enrollment or previous).' );
		}

		$lesson_id = CliResolver::lesson_id( $args[0] );

		$rule = DripArgs::rule(
			(string) $assoc_args['mode'],
			(string) ( $assoc_args['delay'] ?? '' ),
			(string) ( $assoc_args['unit'] ?? DripRule::DEFAULT_UNIT ),
			DripRule::MODES
		);

		Options::set_post_meta( $lesson_id, DripSettings::META_LESSON_RULE, $rule );

		$course_id = (int) Options::get_post_meta( $lesson_id, 'lesson_course_id', 0 );

		\WP_CLI::success( sprintf( 'Lesson #%d: drip %s.', $lesson_id, DripArgs::describe( $rule ) ) );

		if ( DripRule::is_active( $rule ) && $course_id > 0 && ! DripSettings::is_linear( $course_id ) ) {
			\WP_CLI::warning(
				sprintf(
					'Course #%d uses free progression, so the rule stays dormant. Run: wp lw-lms course set-drip %d --progression=linear',
					$course_id,
					$course_id
				)
			);
		}
	}
}
