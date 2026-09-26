<?php
/**
 * Drip Meta registration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\Drip\DripRule;
use LightweightPlugins\LMS\Drip\DripSettings;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Registers the progression and drip meta fields.
 */
final class DripMeta {

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Progression mode of the course.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . DripSettings::META_PROGRESSION,
			[
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => [ DripSettings::PROGRESSION_FREE, DripSettings::PROGRESSION_LINEAR ],
					],
				],
				'single'            => true,
				'type'              => 'string',
				'default'           => DripSettings::PROGRESSION_FREE,
				'sanitize_callback' => [ MetaSanitizers::class, 'progression' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Delay before the course opens.
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . DripSettings::META_COURSE_DELAY,
			[
				'show_in_rest'      => [ 'schema' => self::rule_schema() ],
				'single'            => true,
				'type'              => 'object',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'course_delay' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);

		// Drip rule of a single lesson.
		register_post_meta(
			Lesson::POST_TYPE,
			Options::META_PREFIX . DripSettings::META_LESSON_RULE,
			[
				'show_in_rest'      => [ 'schema' => self::rule_schema() ],
				'single'            => true,
				'type'              => 'object',
				'default'           => [],
				'sanitize_callback' => [ MetaSanitizers::class, 'drip_rule' ],
				'auth_callback'     => [ MetaAuth::class, 'can_edit' ],
			]
		);
	}

	/**
	 * REST schema of a drip rule, shared by the course, section and lesson fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function rule_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'mode'  => [
					'type' => 'string',
					'enum' => DripRule::MODES,
				],
				'value' => [
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => DripRule::MAX_VALUE,
				],
				'unit'  => [
					'type' => 'string',
					'enum' => DripRule::UNITS,
				],
			],
		];
	}
}
