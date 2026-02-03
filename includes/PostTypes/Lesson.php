<?php
/**
 * Lesson custom post type.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\PostTypes;

/**
 * Registers the Lesson custom post type.
 */
final class Lesson {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'lesson';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                  => _x( 'Lessons', 'Post type general name', 'lw-lms' ),
			'singular_name'         => _x( 'Lesson', 'Post type singular name', 'lw-lms' ),
			'menu_name'             => _x( 'Lessons', 'Admin menu text', 'lw-lms' ),
			'add_new'               => __( 'Add New', 'lw-lms' ),
			'add_new_item'          => __( 'Add New Lesson', 'lw-lms' ),
			'edit_item'             => __( 'Edit Lesson', 'lw-lms' ),
			'new_item'              => __( 'New Lesson', 'lw-lms' ),
			'view_item'             => __( 'View Lesson', 'lw-lms' ),
			'view_items'            => __( 'View Lessons', 'lw-lms' ),
			'search_items'          => __( 'Search Lessons', 'lw-lms' ),
			'not_found'             => __( 'No lessons found', 'lw-lms' ),
			'not_found_in_trash'    => __( 'No lessons found in Trash', 'lw-lms' ),
			'all_items'             => __( 'All Lessons', 'lw-lms' ),
			'archives'              => __( 'Lesson Archives', 'lw-lms' ),
			'attributes'            => __( 'Lesson Attributes', 'lw-lms' ),
			'insert_into_item'      => __( 'Insert into lesson', 'lw-lms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this lesson', 'lw-lms' ),
			'filter_items_list'     => __( 'Filter lessons list', 'lw-lms' ),
			'items_list_navigation' => __( 'Lessons list navigation', 'lw-lms' ),
			'items_list'            => __( 'Lessons list', 'lw-lms' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=' . Course::POST_TYPE,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'lesson' ],
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => [
				'title',
				'editor',
				'custom-fields',
				'revisions',
			],
			'template'           => [],
			'template_lock'      => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
