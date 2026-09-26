<?php
/**
 * Options management class.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS;

/**
 * Handles plugin options and settings.
 */
final class Options {

	/**
	 * Option name in database.
	 */
	public const OPTION_NAME = 'lw_lms_options';

	/**
	 * Meta key prefix for post meta.
	 */
	public const META_PREFIX = '_lw_lms_';

	/**
	 * Cached options.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $options = null;

	/**
	 * Get default options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return [
			// General.
			'courses_per_page'         => 10,
			'enable_preview_lessons'   => true,

			// Access.
			'default_access_type'      => 'free',
			'auto_enroll_admins'       => false,

			// Quizzes.
			'quiz_pass_percentage'     => 80,
			'require_quiz_pass'        => false,

			// WooCommerce.
			'woo_enabled'              => true,

			// Advanced.
			'delete_data_on_uninstall' => false,
		];
	}

	/**
	 * Get all options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		if ( null === self::$options ) {
			$saved    = get_option( self::OPTION_NAME, [] );
			$defaults = self::get_defaults();

			// Only known keys: values of removed settings (show_progress_bar)
			// that are still stored are ignored instead of leaking out.
			self::$options = array_intersect_key( wp_parse_args( $saved, $defaults ), $defaults );
		}

		return self::$options;
	}

	/**
	 * Get a single option.
	 *
	 * @param string $key           Option key.
	 * @param mixed  $default_value Default value if not set.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default_value = null ): mixed {
		$options = self::get_all();

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default_value ?? ( self::get_defaults()[ $key ] ?? null );
	}

	/**
	 * Set a single option.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 * @return bool
	 */
	public static function set( string $key, mixed $value ): bool {
		$options         = self::get_all();
		$options[ $key ] = $value;

		return self::save( $options );
	}

	/**
	 * Save all options.
	 *
	 * @param array<string, mixed> $options Options to save.
	 * @return bool
	 */
	public static function save( array $options ): bool {
		self::$options = $options;
		return update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Reset options to defaults.
	 *
	 * @return bool
	 */
	public static function reset(): bool {
		self::$options = null;
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Get post meta value.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $key           Meta key (without prefix).
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public static function get_post_meta( int $post_id, string $key, mixed $default_value = '' ): mixed {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );

		return '' !== $value ? $value : $default_value;
	}

	/**
	 * Set post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (without prefix).
	 * @param mixed  $value   Value to save.
	 * @return bool
	 */
	public static function set_post_meta( int $post_id, string $key, mixed $value ): bool {
		if ( '' === $value || null === $value ) {
			return delete_post_meta( $post_id, self::META_PREFIX . $key );
		}

		return (bool) update_post_meta( $post_id, self::META_PREFIX . $key, $value );
	}

	/**
	 * Clear options cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$options = null;
	}
}
