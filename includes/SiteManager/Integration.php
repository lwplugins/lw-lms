<?php
/**
 * LW Site Manager Integration.
 *
 * Registers LMS abilities when LW Site Manager is active.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager;

/**
 * Hooks into LW Site Manager to register LMS abilities.
 */
final class Integration {

	/**
	 * Initialize hooks. Safe to call even if Site Manager is not active.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'lw_site_manager_register_categories', [ self::class, 'register_category' ] );
		add_action( 'lw_site_manager_register_abilities', [ self::class, 'register_abilities' ] );
	}

	/**
	 * Register the LMS ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'lms',
			[
				'label'       => __( 'LMS', 'lw-lms' ),
				'description' => __( 'Learning management system abilities', 'lw-lms' ),
			]
		);
	}

	/**
	 * Register LMS abilities.
	 *
	 * @param object $permissions Permission manager from Site Manager.
	 * @return void
	 */
	public static function register_abilities( object $permissions ): void {
		LmsAbilities::register( $permissions );
	}
}
