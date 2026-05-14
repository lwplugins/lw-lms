<?php
/**
 * WP-CLI command: lw-lms course list.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * List courses.
 */
final class CourseListCommand {

	/**
	 * List courses.
	 *
	 * ## OPTIONS
	 *
	 * [--access-type=<type>]
	 * : Filter by access type (open|free|paid).
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
	 * default: 100
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
	 *     wp lw-lms course list
	 *     wp lw-lms course list --access-type=paid --format=json
	 *     wp lw-lms course list --format=ids
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI signature.
		$query_args = [
			'post_type'      => Course::POST_TYPE,
			'post_status'    => (string) ( $assoc_args['status'] ?? 'any' ),
			'posts_per_page' => (int) ( $assoc_args['per-page'] ?? 100 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $assoc_args['access-type'] ) ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI listing.
				[
					'key'   => Options::META_PREFIX . 'access_type',
					'value' => (string) $assoc_args['access-type'],
				],
			];
		}

		$posts  = get_posts( $query_args );
		$format = (string) ( $assoc_args['format'] ?? 'table' );

		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', array_map( static fn ( $p ): int => (int) $p->ID, $posts ) ) );
			return;
		}

		$items = array_map(
			static function ( $post ): array {
				return [
					'id'          => (int) $post->ID,
					'title'       => $post->post_title,
					'slug'        => $post->post_name,
					'status'      => $post->post_status,
					'access_type' => Options::get_post_meta( $post->ID, 'access_type', '' ),
					'duration'    => Options::get_post_meta( $post->ID, 'duration', '' ),
				];
			},
			$posts
		);

		\WP_CLI\Utils\format_items( $format, $items, [ 'id', 'title', 'slug', 'status', 'access_type', 'duration' ] );
	}
}
