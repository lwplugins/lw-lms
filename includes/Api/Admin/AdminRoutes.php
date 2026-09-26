<?php
/**
 * Admin REST routes bootstrap.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin;

use WP_Error;
use WP_REST_Request;

/**
 * Registers the lw-lms/v1/admin/* routes the React admin uses, and the
 * helpers they share. The public lms/v1 API is registered elsewhere and is
 * not touched by any of these.
 *
 * REST cookie auth supplies the nonce (X-WP-Nonce), so write routes need no
 * nonce of their own; every route checks a capability.
 */
final class AdminRoutes {

	/**
	 * Admin namespace (kept out of the public lms/v1 API).
	 */
	public const NAMESPACE = 'lw-lms/v1';

	/**
	 * Register every admin route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		( new SettingsController() )->register_routes();
		( new OverviewController() )->register_routes();
		( new LookupController() )->register_routes();
		( new EnrollmentsController() )->register_routes();
		( new QuizAttemptsController() )->register_routes();
		( new QuizErrorController() )->register_routes();
	}

	/**
	 * Settings and the overview: LMS managers and administrators.
	 *
	 * @return bool
	 */
	public static function can_manage_settings(): bool {
		return current_user_can( 'manage_lms' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Learner data (enrollments, quiz attempts): LMS managers only.
	 *
	 * @return bool
	 */
	public static function can_manage_learners(): bool {
		return current_user_can( 'manage_lms' );
	}

	/**
	 * Register one route with a handler per HTTP method.
	 *
	 * @param string                              $path       Route path under the namespace.
	 * @param array<string, string>               $handlers   Method constant => public method name on $owner.
	 * @param object                              $owner      Controller instance.
	 * @param callable                            $permission Permission callback.
	 * @param array<string, array<string, mixed>> $args       Route arguments (URL parameters).
	 * @return void
	 */
	public static function add( string $path, array $handlers, object $owner, callable $permission, array $args = [] ): void {
		$endpoints = [];

		foreach ( $handlers as $methods => $callback ) {
			$endpoints[] = [
				'methods'             => $methods,
				'callback'            => [ $owner, $callback ],
				'permission_callback' => $permission,
				'args'                => $args,
			];
		}

		register_rest_route( self::NAMESPACE, $path, $endpoints );
	}

	/**
	 * A translated REST error.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Translated message.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $data    Extra error data.
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status, array $data = [] ): WP_Error {
		return new WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}

	/**
	 * The "some fields are not valid" error.
	 *
	 * @param array<string, array<int, string>> $fields Field => messages.
	 * @return WP_Error
	 */
	public static function invalid( array $fields ): WP_Error {
		return self::error(
			'lw_lms_invalid',
			__( 'Some fields are not valid. Nothing was saved.', 'lw-lms' ),
			400,
			[ 'fields' => $fields ]
		);
	}

	/**
	 * The "not found" error.
	 *
	 * @return WP_Error
	 */
	public static function not_found(): WP_Error {
		return self::error( 'lw_lms_not_found', __( 'That item no longer exists.', 'lw-lms' ), 404 );
	}

	/**
	 * The "request too large" error, or null when the body fits.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param int             $max_size Largest accepted size in bytes.
	 * @return WP_Error|null
	 */
	public static function too_large( WP_REST_Request $request, int $max_size ): ?WP_Error {
		if ( strlen( (string) $request->get_body() ) <= $max_size ) {
			return null;
		}

		return self::error( 'lw_lms_too_large', __( 'The request is too large.', 'lw-lms' ), 413 );
	}

	/**
	 * Decoded JSON body as an array (empty for an empty or invalid body).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<array-key, mixed>
	 */
	public static function body( WP_REST_Request $request ): array {
		// Null for an empty or undecodable body at runtime, despite the stub.
		$body = $request->get_json_params();

		return empty( $body ) ? [] : $body;
	}
}
