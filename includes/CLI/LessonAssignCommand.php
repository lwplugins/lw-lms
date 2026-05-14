<?php
/**
 * WP-CLI command: lw-lms lesson assign.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Options;

/**
 * Assign (or re-assign) a lesson to a course / section.
 */
final class LessonAssignCommand {

	/**
	 * Assign a lesson to a course / section.
	 *
	 * ## OPTIONS
	 *
	 * <lesson>
	 * : Lesson ID or slug.
	 *
	 * --course=<course>
	 * : Course ID or slug.
	 *
	 * [--section=<section-id>]
	 * : Section ID within the course. Pass an empty string to clear.
	 *
	 * [--order=<order>]
	 * : Lesson order.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson assign 99 --course=42 --section=sec_intro --order=2
	 *
	 * @param array<int, string>    $args       Positional args. [0] = lesson ref.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Lesson ID or slug is required.' );
		}

		if ( empty( $assoc_args['course'] ) ) {
			\WP_CLI::error( '--course is required.' );
		}

		$lesson_id = CliResolver::lesson_id( $args[0] );
		$course_id = CliResolver::course_id( $assoc_args['course'] );

		Options::set_post_meta( $lesson_id, 'lesson_course_id', $course_id );

		if ( array_key_exists( 'section', $assoc_args ) ) {
			$section_value = (string) $assoc_args['section'];
			Options::set_post_meta(
				$lesson_id,
				'lesson_section_id',
				'' === $section_value ? '' : sanitize_key( $section_value )
			);
		}

		if ( isset( $assoc_args['order'] ) ) {
			Options::set_post_meta( $lesson_id, 'lesson_order', (int) $assoc_args['order'] );
		}

		\WP_CLI::success( sprintf( 'Lesson #%d assigned to course #%d.', $lesson_id, $course_id ) );
	}
}
