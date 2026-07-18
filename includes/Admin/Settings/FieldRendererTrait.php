<?php
/**
 * Field Renderer Trait.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Settings;

use LightweightPlugins\LMS\Options;

/**
 * Trait for rendering form fields.
 */
trait FieldRendererTrait {

	/**
	 * Render a text input field.
	 *
	 * @param array{name: string, description?: string} $args Field arguments.
	 * @return void
	 */
	protected function render_text_field( array $args ): void {
		$name  = $args['name'];
		$value = Options::get( $name );
		$desc  = $args['description'] ?? '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME ),
			esc_attr( (string) $value )
		);

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Render a number input field.
	 *
	 * @param array{name: string, description?: string, min?: int, max?: int} $args Field arguments.
	 * @return void
	 */
	protected function render_number_field( array $args ): void {
		$name  = $args['name'];
		$value = Options::get( $name );
		$desc  = $args['description'] ?? '';
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? '';

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="small-text" min="%4$s" %5$s />',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			$max ? 'max="' . esc_attr( (string) $max ) . '"' : ''
		);

		if ( $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array{name: string, label?: string} $args Field arguments.
	 * @return void
	 */
	protected function render_checkbox_field( array $args ): void {
		$name  = $args['name'];
		$label = $args['label'] ?? '';
		$value = Options::get( $name );

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render a select field.
	 *
	 * @param array{name: string, options?: array<array-key, string>} $args Field arguments.
	 * @return void
	 */
	protected function render_select_field( array $args ): void {
		$name    = $args['name'];
		$options = $args['options'] ?? [];
		$value   = Options::get( $name );

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $name ),
			esc_attr( Options::OPTION_NAME )
		);

		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}
}
