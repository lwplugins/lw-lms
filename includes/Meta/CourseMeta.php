<?php
/**
 * Course Meta registration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\Access\NewCourseDefaults;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\Options;

/**
 * Registers course meta fields.
 */
final class CourseMeta {

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Access type.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'access_type',
			[
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => NewCourseDefaults::ACCESS_TYPES,
					],
				],
				'single'            => true,
				'type'              => 'string',
				'default'           => 'free',
				'sanitize_callback' => [ MetaSanitizers::class, 'access_type' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// WooCommerce product IDs.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'product_ids',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'id_list' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// WooCommerce subscription IDs.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'subscription_ids',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'id_list' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// WooCommerce Memberships plan IDs.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'membership_plan_ids',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'id_list' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		SubscriptionVariationMeta::register();

		// WooCommerce product durations (product_id => days).
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'product_durations',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'additionalProperties' => [ 'type' => 'integer' ],
					],
				],
				'single'            => true,
				'type'              => 'object',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'durations' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Preview lesson IDs.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'preview_lesson_ids',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'id_list' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Course sections.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'course_sections',
			[
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'          => [ 'type' => 'string' ],
								'title'       => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
								'order'       => [ 'type' => 'integer' ],
								'drip'        => DripMeta::rule_schema(),
							],
						],
					],
				],
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'sections' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Attachments.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'attachments',
			[
				'show_in_rest'      => [
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
				'single'            => true,
				'type'              => 'array',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'attachments' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Duration.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'duration',
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => [ MetaSanitizers::class, 'text' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Instructor.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'instructor',
			[
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => [ MetaSanitizers::class, 'text' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);
	}
}
