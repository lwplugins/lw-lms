<?php
/**
 * WooCommerce integration.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\WooCommerce;

use LightweightPlugins\LMS\Options;

/**
 * WooCommerce detection and the "Enable WooCommerce integration" switch.
 */
final class WooCommerce {

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Whether the integration runs: WooCommerce is active and the setting is on.
	 *
	 * When off, orders no longer grant or revoke access and purchases,
	 * subscriptions and memberships are not checked. Access rows past orders
	 * already created are kept.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return self::is_active() && (bool) Options::get( 'woo_enabled', true );
	}

	/**
	 * Check if WooCommerce Subscriptions is active.
	 *
	 * @return bool
	 */
	public static function is_subscriptions_active(): bool {
		return class_exists( 'WC_Subscriptions' );
	}
}
