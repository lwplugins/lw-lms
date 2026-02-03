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
 * Handles WooCommerce integration.
 */
final class WooCommerce {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		if ( ! Options::get( 'woo_enabled' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if WooCommerce Subscriptions is active.
	 *
	 * @return bool
	 */
	public static function is_subscriptions_active(): bool {
		return class_exists( 'WC_Subscriptions' );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Show admin notice if WooCommerce is not active.
		add_action( 'admin_notices', [ $this, 'maybe_show_wc_notice' ] );
	}

	/**
	 * Show admin notice if WooCommerce is needed but not active.
	 *
	 * @return void
	 */
	public function maybe_show_wc_notice(): void {
		// Only show on LMS pages.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, [ 'course', 'lesson' ], true ) ) {
			return;
		}

		if ( self::is_active() ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'LW LMS:', 'lw-lms' ); ?></strong>
				<?php esc_html_e( 'WooCommerce is required for paid courses. Please install and activate WooCommerce.', 'lw-lms' ); ?>
			</p>
		</div>
		<?php
	}
}
