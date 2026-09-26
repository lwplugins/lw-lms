<?php
/**
 * Download REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\AttachmentOwners;
use LightweightPlugins\LMS\Api\DownloadAccess;
use LightweightPlugins\LMS\Api\DownloadLink;
use LightweightPlugins\LMS\Api\RestApi;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * Handles protected file downloads.
 */
final class DownloadController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			RestApi::NAMESPACE,
			'/download/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'download' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Handle file download.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error Streams the file and exits on success; returns WP_Error on failure.
	 */
	public function download( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'id' );
		$user_id       = $this->resolve_user( $request, $attachment_id );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return DownloadAccess::not_found();
		}

		// Default deny: only files listed by a course or lesson the user can
		// see and access are served.
		$denied = DownloadAccess::check( AttachmentOwners::find( $attachment_id ), $user_id );

		if ( null !== $denied ) {
			return $denied;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return DownloadAccess::not_found();
		}

		// Fire action.
		do_action( 'lw_lms_attachment_downloaded', $attachment_id, $user_id );

		// Serve file.
		$this->serve_file( $file_path, $attachment );
		exit;
	}

	/**
	 * The user a download is checked for.
	 *
	 * A signed link (see DownloadLink) names its user; otherwise the REST
	 * authentication decides (cookie + nonce, application password, …).
	 *
	 * @param WP_REST_Request $request       Request object.
	 * @param int             $attachment_id Attachment ID.
	 * @return int|WP_Error
	 */
	private function resolve_user( WP_REST_Request $request, int $attachment_id ) {
		$signature = $request->get_param( DownloadLink::ARG_SIGNATURE );

		if ( null === $signature ) {
			return get_current_user_id();
		}

		$user_id = absint( $request->get_param( DownloadLink::ARG_USER ) );
		$expires = absint( $request->get_param( DownloadLink::ARG_EXPIRES ) );

		if ( ! DownloadLink::verify( $attachment_id, $user_id, $expires, (string) $signature ) ) {
			return new WP_Error(
				'download_link_expired',
				__( 'This download link is invalid or has expired. Reload the page to get a new one.', 'lw-lms' ),
				[ 'status' => 403 ]
			);
		}

		return $user_id;
	}

	/**
	 * Serve file for download.
	 *
	 * @param string   $file_path  File path.
	 * @param \WP_Post $attachment Attachment post.
	 * @return void
	 */
	private function serve_file( string $file_path, \WP_Post $attachment ): void {
		$filename  = basename( $file_path );
		$mime_type = get_post_mime_type( $attachment->ID );

		// Clean output buffer.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set headers.
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output file.
		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}
}
