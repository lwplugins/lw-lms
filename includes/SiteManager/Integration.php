<?php
/**
 * LW Site Manager Integration.
 *
 * Registers LMS abilities. Prefers the LW Site Manager bridge (which carries
 * a PermissionManager instance) when active; otherwise falls back to direct
 * Abilities API hooks so abilities still work with only the Abilities API
 * present (WP 6.9+ or feature plugin).
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager;

use LightweightPlugins\LMS\SiteManager\Abilities\AbilityPermissions;
use LightweightPlugins\LMS\SiteManager\Abilities\CourseAbilities;
use LightweightPlugins\LMS\SiteManager\Abilities\OptionsAbilities;
use LightweightPlugins\LMS\SiteManager\Abilities\ProgressAbilities;

/**
 * Hooks ability registration into both Site Manager and Abilities API.
 */
final class Integration {

	private const CATEGORY_ID = 'lms';

	/**
	 * Register hooks. Safe to call when neither Site Manager nor Abilities API is active.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Bridge: prefer Site Manager (carries PermissionManager).
		add_action( 'lw_site_manager_register_categories', [ self::class, 'register_category' ] );
		add_action( 'lw_site_manager_register_abilities', [ self::class, 'register_via_site_manager' ] );

		// Direct fallback when Site Manager is not active. Priority 20 runs after the SM bridge.
		add_action( 'wp_abilities_api_categories_init', [ self::class, 'maybe_register_category_direct' ], 20 );
		add_action( 'wp_abilities_api_init', [ self::class, 'maybe_register_abilities_direct' ], 20 );
	}

	/**
	 * Register the LMS ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_ID,
			[
				'label'       => __( 'LMS', 'lw-lms' ),
				'description' => __( 'Learning management system abilities', 'lw-lms' ),
			]
		);
	}

	/**
	 * Register abilities via the Site Manager bridge (uses its PermissionManager).
	 *
	 * @param object $permissions PermissionManager instance from Site Manager.
	 * @return void
	 */
	public static function register_via_site_manager( object $permissions ): void {
		self::register_abilities( new AbilityPermissions( $permissions ) );
	}

	/**
	 * Register the category directly when Site Manager has not done so.
	 *
	 * @return void
	 */
	public static function maybe_register_category_direct(): void {
		if ( did_action( 'lw_site_manager_register_categories' ) > 0 ) {
			return;
		}

		self::register_category();
	}

	/**
	 * Register abilities directly when Site Manager has not done so.
	 *
	 * @return void
	 */
	public static function maybe_register_abilities_direct(): void {
		if ( did_action( 'lw_site_manager_register_abilities' ) > 0 ) {
			return;
		}

		self::register_abilities( new AbilityPermissions() );
	}

	/**
	 * Run all ability registrations against the given permission factory.
	 *
	 * @param AbilityPermissions $permissions Permission callback factory.
	 * @return void
	 */
	private static function register_abilities( AbilityPermissions $permissions ): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		CourseAbilities::register( $permissions );
		ProgressAbilities::register( $permissions );
		OptionsAbilities::register( $permissions );
	}
}
