<?php
/**
 * WP-CLI command: lw-lms lesson list.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * List lessons for a course.
 */
final class LessonListCommand {

	/**
	 * List lessons.
	 *
	 * ## OPTIONS
	 *
	 * --course=<course>
	 * : Course ID or slug to scope the listing (required).
	 *
	 * [--section=<section-id>]
	 * : Filter by section ID.
	 *
	 * [--status=<status>]
	 * : Post status filter.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--per-page=<n>]
	 * : Maximum rows to return.
	 * ---
	 * default: 200
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lw-lms lesson list --course=42
	 *     wp lw-lms lesson list --course=42 --section=sec_intro --format=json
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
		if ( empty( $assoc_args['course'] ) ) {
			\WP_CLI::error( '--course is required.' );
		}

		$course_id   = CliResolver::course_id( $assoc_args['course'] );
		$meta_clause = [
			'relation' => 'AND',
			[
				'key'     => Options::META_PREFIX . 'lesson_course_id',
				'value'   => $course_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			],
		];

		if ( ! empty( $assoc_args['section'] ) ) {
			$meta_clause[] = [
				'key'   => Options::META_PREFIX . 'lesson_section_id',
				'value' => sanitize_key( (string) $assoc_args['section'] ),
			];
		}

		$posts = get_posts(
			[
				'post_type'      => Lesson::POST_TYPE,
				'post_status'    => (string) ( $assoc_args['status'] ?? 'any' ),
				'posts_per_page' => (int) ( $assoc_args['per-page'] ?? 200 ),
				'meta_key'       => Options::META_PREFIX . 'lesson_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI listing.
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => $meta_clause, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI listing.
			]
		);

		$format = (string) ( $assoc_args['format'] ?? 'table' );

		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', array_map( static fn ( $p ): int => (int) $p->ID, $posts ) ) );
			return;
		}

		$items = array_map(
			static function ( $post ): array {
				return [
					'id'      => (int) $post->ID,
					'title'   => $post->post_title,
					'slug'    => $post->post_name,
					'status'  => $post->post_status,
					'section' => Options::get_post_meta( $post->ID, 'lesson_section_id', '' ),
					'order'   => (int) Options::get_post_meta( $post->ID, 'lesson_order', 0 ),
				];
			},
			$posts
		);

		\WP_CLI\Utils\format_items( $format, $items, [ 'id', 'title', 'slug', 'status', 'section', 'order' ] );
	}
}
