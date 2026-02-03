<?php
/**
 * Course Level taxonomy.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Taxonomies;

use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Registers the Course Level taxonomy.
 */
final class CourseLevel {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'course_level';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                       => _x( 'Levels', 'taxonomy general name', 'lw-lms' ),
			'singular_name'              => _x( 'Level', 'taxonomy singular name', 'lw-lms' ),
			'search_items'               => __( 'Search Levels', 'lw-lms' ),
			'all_items'                  => __( 'All Levels', 'lw-lms' ),
			'edit_item'                  => __( 'Edit Level', 'lw-lms' ),
			'update_item'                => __( 'Update Level', 'lw-lms' ),
			'add_new_item'               => __( 'Add New Level', 'lw-lms' ),
			'new_item_name'              => __( 'New Level Name', 'lw-lms' ),
			'menu_name'                  => __( 'Levels', 'lw-lms' ),
			'separate_items_with_commas' => __( 'Separate levels with commas', 'lw-lms' ),
			'add_or_remove_items'        => __( 'Add or remove levels', 'lw-lms' ),
			'choose_from_most_used'      => __( 'Choose from the most used levels', 'lw-lms' ),
			'not_found'                  => __( 'No levels found', 'lw-lms' ),
			'back_to_items'              => __( '&larr; Back to Levels', 'lw-lms' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'course-level' ],
		];

		register_taxonomy( self::TAXONOMY, Course::POST_TYPE, $args );
	}
}
