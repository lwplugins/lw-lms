<?php
/**
 * Ability permission callback factory.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\SiteManager\Abilities;

/**
 * Builds permission callbacks for ability registrations.
 *
 * Prefers the LW Site Manager PermissionManager when available so the user can
 * configure permissions centrally; falls back to a static capability map when
 * Site Manager is not active (so abilities still register and gate correctly
 * if only the Abilities API is present).
 */
final class AbilityPermissions {

	/**
	 * Site Manager PermissionManager instance, when available.
	 *
	 * @var object|null
	 */
	private ?object $manager;

	/**
	 * Constructor.
	 *
	 * @param object|null $manager Optional PermissionManager instance from Site Manager.
	 */
	public function __construct( ?object $manager = null ) {
		$this->manager = $manager;
	}

	/**
	 * Get a permission callback for the given key.
	 *
	 * @param string $key Permission key (e.g. "can_edit_posts").
	 * @return callable
	 */
	public function callback( string $key ): callable {
		if ( $this->manager && method_exists( $this->manager, 'callback' ) ) {
			return $this->manager->callback( $key );
		}

		return self::fallback_callback( $key );
	}

	/**
	 * Build a fallback callback that maps a permission key to a WP capability.
	 *
	 * @param string $key Permission key.
	 * @return callable
	 */
	private static function fallback_callback( string $key ): callable {
		$map = [
			'can_edit_posts'     => 'edit_posts',
			'can_manage_options' => 'manage_options',
			'can_edit_users'     => 'edit_users',
		];

		$capability = $map[ $key ] ?? 'manage_options';

		return static fn(): bool => current_user_can( $capability );
	}
}
