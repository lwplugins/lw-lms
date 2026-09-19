<?php
/**
 * Drip form fields.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Metaboxes;

use LightweightPlugins\LMS\Drip\DripRule;

/**
 * The delay control (a number plus a unit) shared by the course and lesson
 * drip metaboxes, and the reading of it back out of a submitted form.
 */
trait DripFieldsTrait {

	/**
	 * Render the "how long" pair of fields.
	 *
	 * @param string                                        $prefix Field name prefix.
	 * @param array{mode: string, value: int, unit: string} $rule   Current rule.
	 * @return void
	 */
	private function render_delay_fields( string $prefix, array $rule ): void {
		?>
		<span class="lw-lms-drip-delay">
			<input
				type="number"
				id="<?php echo esc_attr( $prefix . '_value' ); ?>"
				name="<?php echo esc_attr( $prefix . '_value' ); ?>"
				value="<?php echo esc_attr( (string) $rule['value'] ); ?>"
				min="0"
				max="<?php echo esc_attr( (string) DripRule::MAX_VALUE ); ?>"
				step="1"
			/>
			<select id="<?php echo esc_attr( $prefix . '_unit' ); ?>" name="<?php echo esc_attr( $prefix . '_unit' ); ?>">
				<?php foreach ( self::unit_labels() as $unit => $label ) : ?>
					<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $rule['unit'], $unit ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</span>
		<?php
	}

	/**
	 * Read a rule back from a submitted form.
	 *
	 * The caller has already verified the nonce and the capability.
	 *
	 * @param string        $prefix        Field name prefix.
	 * @param string        $mode          Mode submitted with the form.
	 * @param array<string> $allowed_modes Modes accepted in this position.
	 * @return array{mode: string, value: int, unit: string}
	 */
	private function read_rule( string $prefix, string $mode, array $allowed_modes ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by the calling metabox.
		$value = isset( $_POST[ $prefix . '_value' ] ) ? absint( wp_unslash( $_POST[ $prefix . '_value' ] ) ) : 0;
		$unit  = isset( $_POST[ $prefix . '_unit' ] ) ? sanitize_key( wp_unslash( $_POST[ $prefix . '_unit' ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return DripRule::normalize(
			[
				'mode'  => $mode,
				'value' => $value,
				'unit'  => $unit,
			],
			$allowed_modes
		);
	}

	/**
	 * Mode submitted with the form.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	private function read_mode( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the calling metabox.
		return isset( $_POST[ $field ] ) ? sanitize_key( wp_unslash( $_POST[ $field ] ) ) : DripRule::MODE_NONE;
	}

	/**
	 * Unit labels.
	 *
	 * @return array<string, string>
	 */
	private static function unit_labels(): array {
		return [
			'hour'  => __( 'hours', 'lw-lms' ),
			'day'   => __( 'days', 'lw-lms' ),
			'week'  => __( 'weeks', 'lw-lms' ),
			'month' => __( 'months', 'lw-lms' ),
		];
	}
}
