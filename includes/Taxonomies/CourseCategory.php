<?php
/**
 * Course Category taxonomy.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Taxonomies;

use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Registers the Course Category taxonomy.
 */
final class CourseCategory {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'course_category';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                       => _x( 'Categories', 'taxonomy general name', 'lw-lms' ),
			'singular_name'              => _x( 'Category', 'taxonomy singular name', 'lw-lms' ),
			'search_items'               => __( 'Search Categories', 'lw-lms' ),
			'all_items'                  => __( 'All Categories', 'lw-lms' ),
			'parent_item'                => __( 'Parent Category', 'lw-lms' ),
			'parent_item_colon'          => __( 'Parent Category:', 'lw-lms' ),
			'edit_item'                  => __( 'Edit Category', 'lw-lms' ),
			'update_item'                => __( 'Update Category', 'lw-lms' ),
			'add_new_item'               => __( 'Add New Category', 'lw-lms' ),
			'new_item_name'              => __( 'New Category Name', 'lw-lms' ),
			'menu_name'                  => __( 'Categories', 'lw-lms' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'lw-lms' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'lw-lms' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'lw-lms' ),
			'not_found'                  => __( 'No categories found', 'lw-lms' ),
			'back_to_items'              => __( '&larr; Back to Categories', 'lw-lms' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'course-category' ],
		];

		register_taxonomy( self::TAXONOMY, Course::POST_TYPE, $args );
	}
}
