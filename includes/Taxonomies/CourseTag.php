<?php
/**
 * Course Tag taxonomy.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Taxonomies;

use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Registers the Course Tag taxonomy.
 */
final class CourseTag {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'course_tag';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                       => _x( 'Tags', 'taxonomy general name', 'lw-lms' ),
			'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'lw-lms' ),
			'search_items'               => __( 'Search Tags', 'lw-lms' ),
			'all_items'                  => __( 'All Tags', 'lw-lms' ),
			'edit_item'                  => __( 'Edit Tag', 'lw-lms' ),
			'update_item'                => __( 'Update Tag', 'lw-lms' ),
			'add_new_item'               => __( 'Add New Tag', 'lw-lms' ),
			'new_item_name'              => __( 'New Tag Name', 'lw-lms' ),
			'menu_name'                  => __( 'Tags', 'lw-lms' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'lw-lms' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'lw-lms' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'lw-lms' ),
			'not_found'                  => __( 'No tags found', 'lw-lms' ),
			'back_to_items'              => __( '&larr; Back to Tags', 'lw-lms' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'course-tag' ],
		];

		register_taxonomy( self::TAXONOMY, Course::POST_TYPE, $args );
	}
}
