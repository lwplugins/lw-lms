<?php
/**
 * WP-CLI command: lw-lms course set-section.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Options;

/**
 * Create or update a section on a course.
 */
final class CourseSetSectionCommand {

	/**
	 * Create or update a section on a course.
	 *
	 * Sections are stored as an array on course post meta `course_sections`.
	 * Re-running with the same --id updates the existing section in place.
	 *
	 * ## OPTIONS
	 *
	 * <course>
	 * : Course ID or slug.
	 *
	 * --id=<section-id>
	 * : Section identifier (string, e.g. "sec_intro").
	 *
	 * [--title=<title>]
	 * : Section title.
	 *
	 * [--description=<description>]
	 * : Section description.
	 *
	 * [--order=<order>]
	 * : Sort order.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms course set-section 42 --id=sec_intro --title="Intro" --order=0
	 *
	 * @param array<int, string>    $args       Positional args. [0] = course ref.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Course ID or slug is required.' );
		}

		$course_id  = CliResolver::course_id( $args[0] );
		$section_id = (string) ( $assoc_args['id'] ?? '' );

		if ( '' === trim( $section_id ) ) {
			\WP_CLI::error( '--id is required.' );
		}

		$section_id = sanitize_key( $section_id );
		$sections   = Options::get_post_meta( $course_id, 'course_sections', [] );
		if ( ! is_array( $sections ) ) {
			$sections = [];
		}

		$found = false;
		foreach ( $sections as &$section ) {
			if ( ( $section['id'] ?? '' ) !== $section_id ) {
				continue;
			}

			if ( isset( $assoc_args['title'] ) ) {
				$section['title'] = sanitize_text_field( (string) $assoc_args['title'] );
			}
			if ( isset( $assoc_args['description'] ) ) {
				$section['description'] = sanitize_text_field( (string) $assoc_args['description'] );
			}
			if ( isset( $assoc_args['order'] ) ) {
				$section['order'] = (int) $assoc_args['order'];
			}

			$found = true;
			break;
		}
		unset( $section );

		if ( ! $found ) {
			$sections[] = [
				'id'          => $section_id,
				'title'       => sanitize_text_field( (string) ( $assoc_args['title'] ?? '' ) ),
				'description' => sanitize_text_field( (string) ( $assoc_args['description'] ?? '' ) ),
				'order'       => (int) ( $assoc_args['order'] ?? 0 ),
			];
		}

		Options::set_post_meta( $course_id, 'course_sections', $sections );

		\WP_CLI::success(
			sprintf(
				'Course #%d: section %s %s.',
				$course_id,
				$section_id,
				$found ? 'updated' : 'created'
			)
		);
	}
}
