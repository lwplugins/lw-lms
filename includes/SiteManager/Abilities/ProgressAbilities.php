<?php
/**
 * Progress-related ability registrations.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Abilities;

use LightweightPlugins\LMS\SiteManager\Schema\OutputSchemas;
use LightweightPlugins\LMS\SiteManager\Service\ProgressService;

/**
 * Registers get-progress and set-progress abilities.
 */
final class ProgressAbilities {

	/**
	 * Register all progress abilities.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	public static function register( AbilityPermissions $permissions ): void {
		self::register_get_progress( $permissions );
		self::register_set_progress( $permissions );
	}

	/**
	 * Register lw-lms/get-progress ability.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	private static function register_get_progress( AbilityPermissions $permissions ): void {
		wp_register_ability(
			'lw-lms/get-progress',
			[
				'label'               => __( 'Get Progress', 'lw-lms' ),
				'description'         => __( 'Get user progress for a course, including per-lesson completion status.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ ProgressService::class, 'get_progress' ],
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
				'output_schema'       => OutputSchemas::get_progress(),
				'meta'                => AbilityMeta::readonly(),
			]
		);
	}

	/**
	 * Register lw-lms/set-progress ability.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	private static function register_set_progress( AbilityPermissions $permissions ): void {
		wp_register_ability(
			'lw-lms/set-progress',
			[
				'label'               => __( 'Set Progress', 'lw-lms' ),
				'description'         => __( 'Update lesson completion status for a user. Reverting from completed loses the completion timestamp.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ ProgressService::class, 'set_progress' ],
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
				'output_schema'       => OutputSchemas::set_progress(),
				'meta'                => AbilityMeta::write(),
			]
		);
	}
}
