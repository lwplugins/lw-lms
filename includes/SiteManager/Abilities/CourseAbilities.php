<?php
/**
 * Course-related ability registrations.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Abilities;

use LightweightPlugins\LMS\SiteManager\Schema\OutputSchemas;
use LightweightPlugins\LMS\SiteManager\Service\CourseService;

/**
 * Registers list-courses and get-course abilities.
 */
final class CourseAbilities {

	/**
	 * Register all course abilities.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	public static function register( AbilityPermissions $permissions ): void {
		self::register_list_courses( $permissions );
		self::register_get_course( $permissions );
	}

	/**
	 * Register lw-lms/list-courses ability.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	private static function register_list_courses( AbilityPermissions $permissions ): void {
		wp_register_ability(
			'lw-lms/list-courses',
			[
				'label'               => __( 'List Courses', 'lw-lms' ),
				'description'         => __( 'List all published courses with basic metadata.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ CourseService::class, 'list_courses' ],
				'permission_callback' => $permissions->callback( 'can_edit_posts' ),
				'input_schema'        => [
					'type'       => 'object',
					'default'    => [],
					'properties' => [
						'per_page' => [
							'type'        => 'integer',
							'description' => __( 'Number of courses per page. Default: 20.', 'lw-lms' ),
						],
						'page'     => [
							'type'        => 'integer',
							'description' => __( 'Page number. Default: 1.', 'lw-lms' ),
						],
					],
				],
				'output_schema'       => OutputSchemas::list_courses(),
				'meta'                => AbilityMeta::readonly(),
			]
		);
	}

	/**
	 * Register lw-lms/get-course ability.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	private static function register_get_course( AbilityPermissions $permissions ): void {
		wp_register_ability(
			'lw-lms/get-course',
			[
				'label'               => __( 'Get Course', 'lw-lms' ),
				'description'         => __( 'Get full course details including lessons and sections.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ CourseService::class, 'get_course' ],
				'permission_callback' => $permissions->callback( 'can_edit_posts' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'course_id' ],
					'properties' => [
						'course_id' => [
							'type'        => 'integer',
							'description' => __( 'Course post ID.', 'lw-lms' ),
						],
					],
				],
				'output_schema'       => OutputSchemas::get_course(),
				'meta'                => AbilityMeta::readonly(),
			]
		);
	}
}
