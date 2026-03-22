<?php
/**
 * LMS Ability Definitions for LW Site Manager.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager;

/**
 * Registers LMS-specific abilities with the WordPress Abilities API.
 */
final class LmsAbilities {

	/**
	 * Register all LMS abilities.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	public static function register( object $permissions ): void {
		self::register_list_courses( $permissions );
		self::register_get_course( $permissions );
		self::register_get_progress( $permissions );
		self::register_set_progress( $permissions );
		self::register_get_options( $permissions );
	}

	/**
	 * Register lw-lms/list-courses ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_list_courses( object $permissions ): void {
		wp_register_ability(
			'lw-lms/list-courses',
			[
				'label'               => __( 'List Courses', 'lw-lms' ),
				'description'         => __( 'List all published courses with basic metadata.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ LmsService::class, 'list_courses' ],
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
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'courses' => [ 'type' => 'array' ],
						'total'   => [ 'type' => 'integer' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Register lw-lms/get-course ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_get_course( object $permissions ): void {
		wp_register_ability(
			'lw-lms/get-course',
			[
				'label'               => __( 'Get Course', 'lw-lms' ),
				'description'         => __( 'Get full course details including lessons and sections.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ LmsService::class, 'get_course' ],
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
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'course'  => [ 'type' => 'object' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Register lw-lms/get-progress ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_get_progress( object $permissions ): void {
		wp_register_ability(
			'lw-lms/get-progress',
			[
				'label'               => __( 'Get Progress', 'lw-lms' ),
				'description'         => __( 'Get user progress for a course, including per-lesson completion status.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ LmsService::class, 'get_progress' ],
				'permission_callback' => $permissions->callback( 'can_edit_posts' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id', 'course_id' ],
					'properties' => [
						'user_id'   => [
							'type'        => 'integer',
							'description' => __( 'WordPress user ID.', 'lw-lms' ),
						],
						'course_id' => [
							'type'        => 'integer',
							'description' => __( 'Course post ID.', 'lw-lms' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'  => [ 'type' => 'boolean' ],
						'progress' => [ 'type' => 'object' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Register lw-lms/set-progress ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_set_progress( object $permissions ): void {
		wp_register_ability(
			'lw-lms/set-progress',
			[
				'label'               => __( 'Set Progress', 'lw-lms' ),
				'description'         => __( 'Update lesson completion status for a user.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ LmsService::class, 'set_progress' ],
				'permission_callback' => $permissions->callback( 'can_edit_posts' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id', 'course_id', 'lesson_id', 'status' ],
					'properties' => [
						'user_id'   => [
							'type'        => 'integer',
							'description' => __( 'WordPress user ID.', 'lw-lms' ),
						],
						'course_id' => [
							'type'        => 'integer',
							'description' => __( 'Course post ID.', 'lw-lms' ),
						],
						'lesson_id' => [
							'type'        => 'integer',
							'description' => __( 'Lesson post ID.', 'lw-lms' ),
						],
						'status'    => [
							'type'        => 'string',
							'enum'        => [ 'completed', 'in_progress', 'not_started' ],
							'description' => __( 'Completion status: completed, in_progress, or not_started.', 'lw-lms' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'meta'                => self::write_meta(),
			]
		);
	}

	/**
	 * Register lw-lms/get-options ability.
	 *
	 * @param object $permissions Permission manager instance.
	 * @return void
	 */
	private static function register_get_options( object $permissions ): void {
		wp_register_ability(
			'lw-lms/get-options',
			[
				'label'               => __( 'Get LMS Options', 'lw-lms' ),
				'description'         => __( 'Get global LW LMS plugin settings.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ LmsService::class, 'get_options' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'    => 'object',
					'default' => [],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'options' => [ 'type' => 'object' ],
					],
				],
				'meta'                => self::readonly_meta(),
			]
		);
	}

	/**
	 * Read-only ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function readonly_meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}

	/**
	 * Write ability metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function write_meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}
}
