<?php
/**
 * Access Metabox Renderer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Access\AccessChecker;

/**
 * Renders access metabox fields.
 */
trait AccessMetaboxRenderer {

	/**
	 * Render access type radio buttons.
	 *
	 * @param string $access_type Current access type.
	 * @return void
	 */
	private static function render_access_radios( string $access_type ): void {
		$options = [
			AccessChecker::ACCESS_OPEN => __( 'Open (anyone can access)', 'lw-lms' ),
			AccessChecker::ACCESS_FREE => __( 'Free (login required)', 'lw-lms' ),
			AccessChecker::ACCESS_PAID => __( 'Paid (purchase required)', 'lw-lms' ),
		];

		foreach ( $options as $value => $label ) {
			printf(
				'<p><label><input type="radio" name="lw_lms_access_type" value="%s" %s /> %s</label></p>',
				esc_attr( $value ),
				checked( $access_type, $value, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Render products textarea field.
	 *
	 * @param string $value Textarea value.
	 * @return void
	 */
	private static function render_products_field( string $value ): void {
		?>
		<p><strong><?php esc_html_e( 'WooCommerce Products', 'lw-lms' ); ?></strong></p>
		<p>
			<label for="lw_lms_product_ids"><?php esc_html_e( 'Product IDs:', 'lw-lms' ); ?></label>
			<textarea id="lw_lms_product_ids" name="lw_lms_product_ids" class="widefat" rows="3" placeholder="123:180&#10;456:365&#10;789"><?php echo esc_textarea( $value ); ?></textarea>
			<span class="description">
				<?php esc_html_e( 'One per line. Format: product_id:days (e.g., 123:180). Without days = unlimited.', 'lw-lms' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Render subscriptions field.
	 *
	 * @param array $subscription_ids Subscription IDs.
	 * @return void
	 */
	private static function render_subscriptions_field( array $subscription_ids ): void {
		?>
		<p><strong><?php esc_html_e( 'Subscriptions', 'lw-lms' ); ?></strong></p>
		<p>
			<label for="lw_lms_subscription_ids"><?php esc_html_e( 'Subscription IDs:', 'lw-lms' ); ?></label>
			<input type="text" id="lw_lms_subscription_ids" name="lw_lms_subscription_ids" value="<?php echo esc_attr( implode( ',', $subscription_ids ) ); ?>" class="widefat" placeholder="e.g., 789" />
			<span class="description"><?php esc_html_e( 'Comma-separated subscription product IDs.', 'lw-lms' ); ?></span>
		</p>
		<?php
	}
}
