<?php
/**
 * Course custom post type.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\PostTypes;

/**
 * Registers the Course custom post type.
 */
final class Course {

	/**
	 * Post type slug.
	 */
	public const POST_TYPE = 'course';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                  => _x( 'Courses', 'Post type general name', 'lw-lms' ),
			'singular_name'         => _x( 'Course', 'Post type singular name', 'lw-lms' ),
			'menu_name'             => _x( 'Courses', 'Admin menu text', 'lw-lms' ),
			'add_new'               => __( 'Add New', 'lw-lms' ),
			'add_new_item'          => __( 'Add New Course', 'lw-lms' ),
			'edit_item'             => __( 'Edit Course', 'lw-lms' ),
			'new_item'              => __( 'New Course', 'lw-lms' ),
			'view_item'             => __( 'View Course', 'lw-lms' ),
			'view_items'            => __( 'View Courses', 'lw-lms' ),
			'search_items'          => __( 'Search Courses', 'lw-lms' ),
			'not_found'             => __( 'No courses found', 'lw-lms' ),
			'not_found_in_trash'    => __( 'No courses found in Trash', 'lw-lms' ),
			'all_items'             => __( 'All Courses', 'lw-lms' ),
			'archives'              => __( 'Course Archives', 'lw-lms' ),
			'attributes'            => __( 'Course Attributes', 'lw-lms' ),
			'insert_into_item'      => __( 'Insert into course', 'lw-lms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this course', 'lw-lms' ),
			'filter_items_list'     => __( 'Filter courses list', 'lw-lms' ),
			'items_list_navigation' => __( 'Courses list navigation', 'lw-lms' ),
			'items_list'            => __( 'Courses list', 'lw-lms' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'course' ],
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'supports'           => [
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'revisions',
			],
			'template'           => [],
			'template_lock'      => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
