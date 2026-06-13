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
use LightweightPlugins\LMS\Access\MembershipChecker;
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
		$durations        = Options::get_post_meta( $post->ID, 'product_durations', [] );
		$subscription_ids = Options::get_post_meta( $post->ID, 'subscription_ids', [] );
		$variation_pairs  = Options::get_post_meta( $post->ID, 'subscription_variation_ids', [] );
		$products_value   = AccessProductParser::build_textarea( $product_ids, $durations );
		$variations_value = SubscriptionVariationParser::build_textarea( is_array( $variation_pairs ) ? $variation_pairs : [] );
		$membership_ids   = array_map( 'intval', (array) Options::get_post_meta( $post->ID, 'membership_plan_ids', [] ) );
		?>
		<div class="lw-lms-access-settings">
			<?php AccessMetaboxRenderer::render_access_radios( $access_type ); ?>
			<div class="lw-lms-paid-options" style="<?php echo AccessChecker::ACCESS_PAID !== $access_type ? 'display:none;' : ''; ?>">
				<?php if ( WooCommerce::is_active() ) : ?>
					<hr>
					<?php AccessMetaboxRenderer::render_products_field( $products_value ); ?>
					<?php AccessMetaboxRenderer::render_subscriptions_field( is_array( $subscription_ids ) ? $subscription_ids : [] ); ?>
					<?php AccessMetaboxRenderer::render_subscription_variations_field( $variations_value ); ?>
					<?php AccessMetaboxRenderer::render_memberships_field( $membership_ids ); ?>
				<?php else : ?>
					<hr>
					<p class="description"><?php esc_html_e( 'WooCommerce is required for paid courses.', 'lw-lms' ); ?></p>
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

		if ( isset( $_POST['lw_lms_access_type'] ) ) {
			Options::set_post_meta( $post_id, 'access_type', sanitize_key( $_POST['lw_lms_access_type'] ) );
		}

		if ( isset( $_POST['lw_lms_product_ids'] ) ) {
			$raw                             = sanitize_textarea_field( wp_unslash( $_POST['lw_lms_product_ids'] ) );
			list( $product_ids, $durations ) = AccessProductParser::parse( $raw );
			Options::set_post_meta( $post_id, 'product_ids', $product_ids );
			Options::set_post_meta( $post_id, 'product_durations', $durations );
		}

		if ( isset( $_POST['lw_lms_subscription_ids'] ) ) {
			$ids = array_filter(
				array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['lw_lms_subscription_ids'] ) ) ) )
			);
			Options::set_post_meta( $post_id, 'subscription_ids', $ids );
		}

		if ( isset( $_POST['lw_lms_subscription_variation_ids'] ) ) {
			$raw   = sanitize_textarea_field( wp_unslash( $_POST['lw_lms_subscription_variation_ids'] ) );
			$pairs = SubscriptionVariationParser::parse( $raw );
			Options::set_post_meta( $post_id, 'subscription_variation_ids', $pairs );
		}

		if ( isset( $_POST['lw_lms_memberships_present'] ) && MembershipChecker::is_active() ) {
			$membership_ids = isset( $_POST['lw_lms_membership_plan_ids'] ) && is_array( $_POST['lw_lms_membership_plan_ids'] )
				? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['lw_lms_membership_plan_ids'] ) ) ) )
				: [];
			Options::set_post_meta( $post_id, 'membership_plan_ids', $membership_ids );
		}
	}
}
