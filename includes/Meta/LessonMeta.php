<?php
/**
 * Lesson Meta registration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Options;

/**
 * Registers lesson meta fields.
 */
final class LessonMeta {

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Course ID.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'lesson_course_id',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);

		// Section ID.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'lesson_section_id',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'default'       => '',
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);

		// Order.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'lesson_order',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);

		// Video.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'video',
			[
				'show_in_rest'  => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'url'      => [ 'type' => 'string' ],
							'provider' => [ 'type' => 'string' ],
							'video_id' => [ 'type' => 'string' ],
							'embed'    => [ 'type' => 'string' ],
							'duration' => [ 'type' => 'string' ],
						],
					],
				],
				'single'        => true,
				'type'          => 'object',
				'default'       => [],
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);

		// Attachments.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'attachments',
			[
				'show_in_rest'  => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'          => [ 'type' => 'integer' ],
								'title'       => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
							],
						],
					],
				],
				'single'        => true,
				'type'          => 'array',
				'default'       => [],
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);

		// Duration.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . 'duration',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'default'       => '',
				'auth_callback' => [ self::class, 'can_edit' ],
			]
		);
	}

	/**
	 * Auth callback for meta fields.
	 *
	 * @return bool
	 */
	public static function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}
}
