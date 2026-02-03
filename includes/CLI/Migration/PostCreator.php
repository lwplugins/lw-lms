<?php
/**
 * Post Creator for LearnDash migration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\CLI\Migration;

/**
 * Creates new posts from LearnDash source posts.
 */
final class PostCreator {

	/**
	 * Create a new post from a source post.
	 *
	 * @param \WP_Post $source    Source post.
	 * @param string   $post_type Target post type.
	 * @return int|\WP_Error New post ID or error.
	 */
	public static function create( \WP_Post $source, string $post_type ): int|\WP_Error {
		$args = [
			'post_type'     => $post_type,
			'post_title'    => $source->post_title,
			'post_content'  => $source->post_content,
			'post_excerpt'  => $source->post_excerpt,
			'post_status'   => $source->post_status,
			'post_author'   => $source->post_author,
			'post_date'     => $source->post_date,
			'post_date_gmt' => $source->post_date_gmt,
			'post_name'     => $source->post_name,
		];

		if ( $source->menu_order ) {
			$args['menu_order'] = $source->menu_order;
		}

		return wp_insert_post( $args );
	}

	/**
	 * Copy featured image from source to target.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $target_id Target post ID.
	 * @return void
	 */
	public static function copy_thumbnail( int $source_id, int $target_id ): void {
		$thumbnail_id = get_post_thumbnail_id( $source_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $target_id, $thumbnail_id );
		}
	}
}
