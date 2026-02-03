<?php
/**
 * Course Access Metabox.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\WooCommerce\WooCommerce;

/**
 * Handles the Course Access metabox.
 */
final class CourseAccessMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'save_post_' . Course::POST_TYPE, [ $this, 'save' ] );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'lw_lms_course_access',
			__( 'Access Settings', 'lw-lms' ),
			[ $this, 'render' ],
			Course::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'lw_lms_course_access', 'lw_lms_course_access_nonce' );

		$access_type      = Options::get_post_meta( $post->ID, 'access_type', AccessChecker::ACCESS_FREE );
		$product_ids      = Options::get_post_meta( $post->ID, 'product_ids', [] );
		$subscription_ids = Options::get_post_meta( $post->ID, 'subscription_ids', [] );
		?>
		<div class="lw-lms-access-settings">
			<p>
				<label>
					<input type="radio" name="lw_lms_access_type" value="open" <?php checked( $access_type, AccessChecker::ACCESS_OPEN ); ?> />
					<?php esc_html_e( 'Open (anyone can access)', 'lw-lms' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" name="lw_lms_access_type" value="free" <?php checked( $access_type, AccessChecker::ACCESS_FREE ); ?> />
					<?php esc_html_e( 'Free (login required)', 'lw-lms' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" name="lw_lms_access_type" value="paid" <?php checked( $access_type, AccessChecker::ACCESS_PAID ); ?> />
					<?php esc_html_e( 'Paid (purchase required)', 'lw-lms' ); ?>
				</label>
			</p>

			<div class="lw-lms-paid-options" style="<?php echo AccessChecker::ACCESS_PAID !== $access_type ? 'display:none;' : ''; ?>">
				<?php if ( WooCommerce::is_active() ) : ?>
					<hr>
					<p><strong><?php esc_html_e( 'WooCommerce Products', 'lw-lms' ); ?></strong></p>
					<p>
						<label for="lw_lms_product_ids"><?php esc_html_e( 'Product IDs:', 'lw-lms' ); ?></label>
						<input type="text" id="lw_lms_product_ids" name="lw_lms_product_ids" value="<?php echo esc_attr( implode( ',', $product_ids ) ); ?>" class="widefat" placeholder="e.g., 123, 456" />
						<span class="description"><?php esc_html_e( 'Comma-separated product IDs.', 'lw-lms' ); ?></span>
					</p>

					<p><strong><?php esc_html_e( 'Subscriptions', 'lw-lms' ); ?></strong></p>
					<p>
						<label for="lw_lms_subscription_ids"><?php esc_html_e( 'Subscription IDs:', 'lw-lms' ); ?></label>
						<input type="text" id="lw_lms_subscription_ids" name="lw_lms_subscription_ids" value="<?php echo esc_attr( implode( ',', $subscription_ids ) ); ?>" class="widefat" placeholder="e.g., 789" />
						<span class="description"><?php esc_html_e( 'Comma-separated subscription product IDs.', 'lw-lms' ); ?></span>
					</p>
				<?php else : ?>
					<hr>
					<p class="description">
						<?php esc_html_e( 'WooCommerce is required for paid courses.', 'lw-lms' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(function($) {
			$('input[name="lw_lms_access_type"]').on('change', function() {
				$('.lw-lms-paid-options').toggle($(this).val() === 'paid');
			});
		});
		</script>
		<?php
	}

	/**
	 * Save the metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['lw_lms_course_access_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['lw_lms_course_access_nonce'] ), 'lw_lms_course_access' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save access type.
		if ( isset( $_POST['lw_lms_access_type'] ) ) {
			$access_type = sanitize_key( $_POST['lw_lms_access_type'] );
			Options::set_post_meta( $post_id, 'access_type', $access_type );
		}

		// Save product IDs.
		if ( isset( $_POST['lw_lms_product_ids'] ) ) {
			$product_ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['lw_lms_product_ids'] ) ) ) ) );
			Options::set_post_meta( $post_id, 'product_ids', $product_ids );
		}

		// Save subscription IDs.
		if ( isset( $_POST['lw_lms_subscription_ids'] ) ) {
			$subscription_ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['lw_lms_subscription_ids'] ) ) ) ) );
			Options::set_post_meta( $post_id, 'subscription_ids', $subscription_ids );
		}
	}
}
