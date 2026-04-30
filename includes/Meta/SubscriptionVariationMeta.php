<?php
/**
 * Subscription Variation Meta registration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\Options;

/**
 * Registers the subscription_variation_ids course meta.
 */
final class SubscriptionVariationMeta {

	/**
	 * Register the meta field.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_meta(
			Course::POST_TYPE,
			Options::META_PREFIX . 'subscription_variation_ids',
			[
				'show_in_rest'  => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'    => 'string',
							'pattern' => '^\\d+:\\d+$',
						],
					],
				],
				'single'        => true,
				'type'          => 'array',
				'default'       => [],
				'auth_callback' => [ CourseMeta::class, 'can_edit' ],
			]
		);
	}
}
