<?php
/**
 * Options-related ability registrations.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Abilities;

use LightweightPlugins\LMS\SiteManager\Schema\OutputSchemas;
use LightweightPlugins\LMS\SiteManager\Service\OptionsService;

/**
 * Registers the get-options ability.
 */
final class OptionsAbilities {

	/**
	 * Register all options abilities.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	public static function register( AbilityPermissions $permissions ): void {
		wp_register_ability(
			'lw-lms/get-options',
			[
				'label'               => __( 'Get LMS Options', 'lw-lms' ),
				'description'         => __( 'Get global LW LMS plugin settings.', 'lw-lms' ),
				'category'            => 'lms',
				'execute_callback'    => [ OptionsService::class, 'get_options' ],
				'permission_callback' => $permissions->callback( 'can_manage_options' ),
				'input_schema'        => [
					'type'    => 'object',
					'default' => [],
				],
				'output_schema'       => OutputSchemas::get_options(),
				'meta'                => AbilityMeta::readonly(),
			]
		);
	}
}
