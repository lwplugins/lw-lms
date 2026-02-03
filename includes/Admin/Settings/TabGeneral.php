<?php
/**
 * General Settings Tab.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Settings;

/**
 * General settings tab.
 */
final class TabGeneral implements TabInterface {

	use FieldRendererTrait;

	/**
	 * Get the tab slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'general';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'General', 'lw-lms' );
	}

	/**
	 * Get the tab icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	/**
	 * Render the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<h2><?php esc_html_e( 'General Settings', 'lw-lms' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="courses_per_page"><?php esc_html_e( 'Courses per Page', 'lw-lms' ); ?></label>
				</th>
				<td>
					<?php
					$this->render_number_field(
						[
							'name'        => 'courses_per_page',
							'description' => __( 'Number of courses to show per page in API responses.', 'lw-lms' ),
							'min'         => 1,
							'max'         => 100,
						]
					);
					?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="default_access_type"><?php esc_html_e( 'Default Access Type', 'lw-lms' ); ?></label>
				</th>
				<td>
					<?php
					$this->render_select_field(
						[
							'name'    => 'default_access_type',
							'options' => [
								'open' => __( 'Open (anyone)', 'lw-lms' ),
								'free' => __( 'Free (login required)', 'lw-lms' ),
								'paid' => __( 'Paid (purchase required)', 'lw-lms' ),
							],
						]
					);
					?>
					<p class="description"><?php esc_html_e( 'Default access type for new courses.', 'lw-lms' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Features', 'lw-lms' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'show_progress_bar',
							'label' => __( 'Show progress bar in course listings', 'lw-lms' ),
						]
					);
					?>
					<br><br>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'enable_preview_lessons',
							'label' => __( 'Enable preview lessons feature', 'lw-lms' ),
						]
					);
					?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'WooCommerce', 'lw-lms' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Integration', 'lw-lms' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'woo_enabled',
							'label' => __( 'Enable WooCommerce integration for paid courses', 'lw-lms' ),
						]
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Requires WooCommerce. Enable to sell courses via WooCommerce products.', 'lw-lms' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
