<?php
/**
 * Settings Page class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\Admin\Settings\TabInterface;
use LightweightPlugins\LMS\Admin\Settings\TabGeneral;
use LightweightPlugins\LMS\Admin\Settings\TabAdvanced;
use LightweightPlugins\LMS\Options;

/**
 * Handles the plugin settings page.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const SLUG = 'lw-lms';

	/**
	 * Settings group.
	 */
	private const SETTINGS_GROUP = 'lw_lms_settings';

	/**
	 * Registered tabs.
	 *
	 * @var array<TabInterface>
	 */
	private array $tabs = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_tabs();

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings tabs.
	 *
	 * @return void
	 */
	private function register_tabs(): void {
		$tabs = [
			new TabGeneral(),
			new TabAdvanced(),
		];

		/**
		 * Filter the registered settings tabs.
		 *
		 * Third-party plugins can append, remove, or reorder tabs by returning a
		 * modified list. Each entry must implement TabInterface; non-implementing
		 * values are dropped.
		 *
		 * @param array<TabInterface> $tabs Currently registered tabs.
		 */
		$tabs = apply_filters( 'lw_lms_settings_tabs', $tabs );

		$this->tabs = array_values(
			array_filter(
				$tabs,
				// A third-party filter can return anything despite the @param
				// docblock, so this guard is real at runtime.
				// @phpstan-ignore instanceof.alwaysTrue
				static fn ( $tab ): bool => $tab instanceof TabInterface
			)
		);
	}

	/**
	 * Get the settings group identifier.
	 *
	 * Exposed so third-party plugins can register_setting() against the same
	 * group and save their option alongside core LMS settings through the
	 * existing form (single nonce, single submit).
	 *
	 * @return string
	 */
	public static function get_settings_group(): string {
		return self::SETTINGS_GROUP;
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		ParentPage::maybe_register();

		add_submenu_page(
			ParentPage::SLUG,
			__( 'LMS Settings', 'lw-lms' ),
			__( 'LMS', 'lw-lms' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Options::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => Options::get_defaults(),
			]
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Input values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$defaults  = Options::get_defaults();
		$sanitized = [];

		foreach ( $defaults as $key => $default_val ) {
			if ( is_bool( $default_val ) ) {
				$sanitized[ $key ] = ! empty( $input[ $key ] );
			} elseif ( is_int( $default_val ) ) {
				$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $default_val;
			} else {
				$sanitized[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default_val;
			}
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1>
				<img src="<?php echo esc_url( LW_LMS_URL . 'assets/img/title-icon.svg' ); ?>" alt="" class="lw-title-icon" />
				<?php esc_html_e( 'Lightweight LMS', 'lw-lms' ); ?>
				<span style="font-size: 13px; font-weight: 400; color: #888;">(<?php echo esc_html( LW_LMS_VERSION ); ?>)</span>
			</h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<div class="lw-lms-settings">
					<?php $this->render_tabs_nav(); ?>

					<div class="lw-lms-tab-content">
						<?php $this->render_tabs_content(); ?>
						<?php submit_button(); ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render tabs navigation.
	 *
	 * @return void
	 */
	private function render_tabs_nav(): void {
		?>
		<ul class="lw-lms-tabs">
			<?php foreach ( $this->tabs as $index => $tab ) : ?>
				<li>
					<a href="#<?php echo esc_attr( $tab->get_slug() ); ?>" <?php echo 0 === $index ? 'class="active"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab->get_icon() ); ?>"></span>
						<?php echo esc_html( $tab->get_label() ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render tabs content.
	 *
	 * @return void
	 */
	private function render_tabs_content(): void {
		foreach ( $this->tabs as $index => $tab ) {
			$active_class = 0 === $index ? ' active' : '';
			printf(
				'<div id="tab-%s" class="lw-lms-tab-panel%s">',
				esc_attr( $tab->get_slug() ),
				esc_attr( $active_class )
			);
			$tab->render();
			echo '</div>';
		}
	}
}
