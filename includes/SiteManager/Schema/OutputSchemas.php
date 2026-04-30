<?php
/**
 * Output schemas for LW Site Manager abilities.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Schema;

/**
 * Detailed JSON schemas for ability outputs so AI agents can introspect them.
 */
final class OutputSchemas {

	/**
	 * Schema for the lw-lms/list-courses ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function list_courses(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success'     => [ 'type' => 'boolean' ],
				'courses'     => [
					'type'  => 'array',
					'items' => self::course_summary(),
				],
				'total'       => [ 'type' => 'integer' ],
				'total_pages' => [ 'type' => 'integer' ],
				'page'        => [ 'type' => 'integer' ],
				'per_page'    => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * Schema for the lw-lms/get-course ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_course(): array {
		$course = self::course_summary();

		$course['properties']['content']  = [ 'type' => 'string' ];
		$course['properties']['excerpt']  = [ 'type' => 'string' ];
		$course['properties']['sections'] = [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'id'          => [ 'type' => 'string' ],
					'title'       => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'order'       => [ 'type' => 'integer' ],
				],
			],
		];
		$course['properties']['lessons']  = [
			'type'  => 'array',
			'items' => self::lesson_summary(),
		];

		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'course'  => $course,
			],
		];
	}

	/**
	 * Schema for the lw-lms/get-progress ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_progress(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success'  => [ 'type' => 'boolean' ],
				'progress' => [
					'type'       => 'object',
					'properties' => [
						'user_id'           => [ 'type' => 'integer' ],
						'course_id'         => [ 'type' => 'integer' ],
						'percentage'        => [ 'type' => 'integer' ],
						'completed_lessons' => [ 'type' => 'integer' ],
						'total_lessons'     => [ 'type' => 'integer' ],
						'is_completed'      => [ 'type' => 'boolean' ],
						'lessons'           => [
							'type'                 => 'object',
							'additionalProperties' => [
								'type'       => 'object',
								'properties' => [
									'status'       => [
										'type' => 'string',
										'enum' => [ 'completed', 'in_progress', 'not_started' ],
									],
									'completed_at' => [ 'type' => [ 'string', 'null' ] ],
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Schema for the lw-lms/set-progress ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function set_progress(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'message' => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Schema for the lw-lms/get-options ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'success' => [ 'type' => 'boolean' ],
				'options' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		];
	}

	/**
	 * Reusable course summary schema fragment.
	 *
	 * @return array<string, mixed>
	 */
	private static function course_summary(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'title'       => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'url'         => [ 'type' => 'string' ],
				'access_type' => [
					'type' => 'string',
					'enum' => [ 'open', 'free', 'paid' ],
				],
				'duration'    => [ 'type' => 'string' ],
				'instructor'  => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Reusable lesson summary schema fragment.
	 *
	 * @return array<string, mixed>
	 */
	private static function lesson_summary(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'title'      => [ 'type' => 'string' ],
				'section_id' => [ 'type' => 'string' ],
				'order'      => [ 'type' => 'integer' ],
				'duration'   => [ 'type' => 'string' ],
			],
		];
	}
}
