<?php
/**
 * Advanced Settings Tab.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Settings;

/**
 * Advanced settings tab.
 */
final class TabAdvanced implements TabInterface {

	use FieldRendererTrait;

	/**
	 * Get the tab slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'advanced';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Advanced', 'lw-lms' );
	}

	/**
	 * Get the tab icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'dashicons-admin-tools';
	}

	/**
	 * Render the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<h2><?php esc_html_e( 'Advanced Settings', 'lw-lms' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Data Removal', 'lw-lms' ); ?></th>
				<td>
					<?php
					$this->render_checkbox_field(
						[
							'name'  => 'delete_data_on_uninstall',
							'label' => __( 'Delete all data when plugin is uninstalled', 'lw-lms' ),
						]
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Warning: This will permanently delete all courses, lessons, and progress data when the plugin is uninstalled.', 'lw-lms' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'REST API', 'lw-lms' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'API Namespace', 'lw-lms' ); ?></th>
				<td>
					<code>lms/v1</code>
					<p class="description">
						<?php esc_html_e( 'Base URL:', 'lw-lms' ); ?>
						<code><?php echo esc_url( rest_url( 'lms/v1/' ) ); ?></code>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Available Endpoints', 'lw-lms' ); ?></th>
				<td>
					<ul style="margin: 0;">
						<li><code>GET /courses</code> - <?php esc_html_e( 'List courses', 'lw-lms' ); ?></li>
						<li><code>GET /courses/{id}</code> - <?php esc_html_e( 'Course details', 'lw-lms' ); ?></li>
						<li><code>GET /lessons/{id}</code> - <?php esc_html_e( 'Lesson content', 'lw-lms' ); ?></li>
						<li><code>GET /progress</code> - <?php esc_html_e( 'User progress', 'lw-lms' ); ?></li>
						<li><code>POST /progress</code> - <?php esc_html_e( 'Update progress', 'lw-lms' ); ?></li>
						<li><code>GET /download/{id}</code> - <?php esc_html_e( 'Download attachment', 'lw-lms' ); ?></li>
					</ul>
				</td>
			</tr>
		</table>
		<?php
	}
}
