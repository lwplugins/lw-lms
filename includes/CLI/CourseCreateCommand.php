<?php
/**
 * WP-CLI command: lw-lms course create.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Create a course.
 */
final class CourseCreateCommand {

	private const ACCESS_TYPES = [
		AccessChecker::ACCESS_OPEN,
		AccessChecker::ACCESS_FREE,
		AccessChecker::ACCESS_PAID,
	];

	/**
	 * Create a course.
	 *
	 * ## OPTIONS
	 *
	 * --title=<title>
	 * : Course title (required).
	 *
	 * [--access-type=<type>]
	 * : Access type. Allowed: open, free, paid.
	 * ---
	 * default: free
	 * options:
	 *   - open
	 *   - free
	 *   - paid
	 * ---
	 *
	 * [--duration=<duration>]
	 * : Course duration string (e.g. "8h").
	 *
	 * [--status=<status>]
	 * : Post status.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--excerpt=<excerpt>]
	 * : Course excerpt.
	 *
	 * [--content=<content>]
	 * : Course post_content.
	 *
	 * [--porcelain]
	 * : Output just the new course ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms course create --title="My Course" --access-type=paid --duration="8h"
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

		$access_type = (string) ( $assoc_args['access-type'] ?? AccessChecker::ACCESS_FREE );
		if ( ! in_array( $access_type, self::ACCESS_TYPES, true ) ) {
			\WP_CLI::error(
				sprintf(
					'Invalid --access-type: %s. Allowed: %s',
					$access_type,
					implode( ', ', self::ACCESS_TYPES )
				)
			);
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => Course::POST_TYPE,
				'post_status'  => (string) ( $assoc_args['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( $title ),
				'post_excerpt' => isset( $assoc_args['excerpt'] ) ? wp_kses_post( (string) $assoc_args['excerpt'] ) : '',
				'post_content' => (string) ( $assoc_args['content'] ?? '' ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::error( $post_id->get_error_message() );
		}

		Options::set_post_meta( $post_id, 'access_type', $access_type );

		if ( isset( $assoc_args['duration'] ) ) {
			Options::set_post_meta( $post_id, 'duration', sanitize_text_field( (string) $assoc_args['duration'] ) );
		}

		if ( isset( $assoc_args['porcelain'] ) ) {
			\WP_CLI::log( (string) $post_id );
			return;
		}

		\WP_CLI::success( sprintf( 'Course #%d created: %s', $post_id, $title ) );
	}
}
