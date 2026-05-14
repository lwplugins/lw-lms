<?php
/**
 * WP-CLI command: lw-lms lesson create.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Create a lesson and assign it to a course.
 */
final class LessonCreateCommand {

	/**
	 * Create a lesson.
	 *
	 * ## OPTIONS
	 *
	 * --title=<title>
	 * : Lesson title (required).
	 *
	 * --course=<course>
	 * : Course ID or slug to assign the lesson to (required).
	 *
	 * [--section=<section-id>]
	 * : Section ID within the course (e.g. "sec_intro").
	 *
	 * [--order=<order>]
	 * : Lesson order within its section/course.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--duration=<duration>]
	 * : Lesson duration string.
	 *
	 * [--status=<status>]
	 * : Post status.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--content=<content>]
	 * : Lesson post_content.
	 *
	 * [--porcelain]
	 * : Output just the new lesson ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson create --title="Intro" --course=42 --section=sec_intro --order=1
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
		$title = (string) ( $assoc_args['title'] ?? '' );
		if ( '' === trim( $title ) ) {
			\WP_CLI::error( '--title is required.' );
		}

		if ( empty( $assoc_args['course'] ) ) {
			\WP_CLI::error( '--course is required.' );
		}

		$course_id = CliResolver::course_id( $assoc_args['course'] );

		$post_id = wp_insert_post(
			[
				'post_type'    => Lesson::POST_TYPE,
				'post_status'  => (string) ( $assoc_args['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => (string) ( $assoc_args['content'] ?? '' ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::error( $post_id->get_error_message() );
		}

		Options::set_post_meta( $post_id, 'lesson_course_id', $course_id );
		Options::set_post_meta( $post_id, 'lesson_order', (int) ( $assoc_args['order'] ?? 0 ) );

		if ( isset( $assoc_args['section'] ) ) {
			Options::set_post_meta( $post_id, 'lesson_section_id', sanitize_key( (string) $assoc_args['section'] ) );
		}

		if ( isset( $assoc_args['duration'] ) ) {
			Options::set_post_meta( $post_id, 'duration', sanitize_text_field( (string) $assoc_args['duration'] ) );
		}

		if ( isset( $assoc_args['porcelain'] ) ) {
			\WP_CLI::log( (string) $post_id );
			return;
		}

		\WP_CLI::success( sprintf( 'Lesson #%d created in course #%d.', $post_id, $course_id ) );
	}
}
