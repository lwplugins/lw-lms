<?php
/**
 * Settings REST controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use LightweightPlugins\LMS\Api\Admin\Settings\SettingsInput;
use LightweightPlugins\LMS\Api\Admin\Settings\SettingsResponse;
use LightweightPlugins\LMS\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET/POST lw-lms/v1/admin/settings.
 */
final class SettingsController {

	/**
	 * Largest accepted request body, in bytes (the whole form is well under 1 KB).
	 */
	public const MAX_BYTES = 16384;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		AdminRoutes::add(
			'/admin/settings',
			[
				WP_REST_Server::READABLE  => 'get_settings',
				WP_REST_Server::CREATABLE => 'save_settings',
			],
			$this,
			[ AdminRoutes::class, 'can_manage_settings' ]
		);
	}

	/**
	 * Current state.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( SettingsResponse::build() );
	}

	/**
	 * Partial, atomic update: only the submitted keys change (a switch that
	 * is not sent is never turned off), and nothing is saved when any of
	 * them is invalid.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$too_large = AdminRoutes::too_large( $request, self::MAX_BYTES );

		if ( null !== $too_large ) {
			return $too_large;
		}

		$input = new SettingsInput( AdminRoutes::body( $request ) );

		if ( [] !== $input->errors() ) {
			return AdminRoutes::error(
				'lw_lms_invalid',
				__( 'Some settings are not valid. Nothing was saved.', 'lw-lms' ),
				400,
				[ 'fields' => $input->errors() ]
			);
		}

		if ( [] !== $input->values() ) {
			// Merged over every stored option, so keys that were not sent
			// keep their value.
			Options::save( array_merge( SettingsResponse::options(), $input->values() ) );
		}

		return new WP_REST_Response( SettingsResponse::build() );
	}
}
