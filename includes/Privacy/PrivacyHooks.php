<?php
/**
 * Privacy hooks.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Privacy;

/**
 * Registers the personal data exporter and eraser (Tools → Export / Erase
 * Personal Data) and removes a deleted user's LMS rows.
 */
final class PrivacyHooks {

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'add_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'add_eraser' ] );
		add_action( 'deleted_user', [ PersonalDataEraser::class, 'on_user_deleted' ] );
	}

	/**
	 * Add the LMS exporter.
	 *
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public static function add_exporter( array $exporters ): array {
		$exporters['lw-lms'] = [
			'exporter_friendly_name' => __( 'LW LMS', 'lw-lms' ),
			'callback'               => [ PersonalDataExporter::class, 'export' ],
		];

		return $exporters;
	}

	/**
	 * Add the LMS eraser.
	 *
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public static function add_eraser( array $erasers ): array {
		$erasers['lw-lms'] = [
			'eraser_friendly_name' => __( 'LW LMS', 'lw-lms' ),
			'callback'             => [ PersonalDataEraser::class, 'erase' ],
		];

		return $erasers;
	}
}
