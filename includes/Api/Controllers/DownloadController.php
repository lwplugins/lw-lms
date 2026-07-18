<?php
/**
 * Download REST Controller.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Controllers;

use LightweightPlugins\LMS\Api\RestApi;
use LightweightPlugins\LMS\Access\AccessChecker;
use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
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
		$user_id       = get_current_user_id();

		// Check if attachment exists.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'File not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		// Find parent (course or lesson).
		$access_granted = $this->check_attachment_access( $attachment_id, $user_id );

		if ( is_wp_error( $access_granted ) ) {
			return $access_granted;
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error(
				'not_found',
				__( 'File not found.', 'lw-lms' ),
				[ 'status' => 404 ]
			);
		}

		// Fire action.
		do_action( 'lw_lms_attachment_downloaded', $attachment_id, $user_id );

		// Serve file.
		$this->serve_file( $file_path, $attachment );
		exit;
	}

	/**
	 * Check if user has access to attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       User ID.
	 * @return bool|WP_Error
	 */
	private function check_attachment_access( int $attachment_id, int $user_id ): bool|WP_Error {
		// Find which course or lesson this attachment belongs to.
		$parent = $this->find_attachment_parent( $attachment_id );

		if ( ! $parent ) {
			// Not attached to any LMS content - allow download.
			return true;
		}

		// Check access based on parent type.
		if ( 'course' === $parent['type'] ) {
			if ( ! AccessChecker::has_course_access( $parent['id'], $user_id ) ) {
				return $this->get_access_error( $parent['id'], $user_id );
			}
		} elseif ( 'lesson' === $parent['type'] ) {
			if ( ! AccessChecker::has_lesson_access( $parent['id'], $user_id ) ) {
				$course_id = (int) Options::get_post_meta( $parent['id'], 'lesson_course_id', 0 );
				return $this->get_access_error( $course_id, $user_id );
			}
		}

		return true;
	}

	/**
	 * Find the parent (course or lesson) of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	private function find_attachment_parent( int $attachment_id ): ?array {
		global $wpdb;

		// Check courses.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$course_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value LIKE %s",
				Options::META_PREFIX . 'attachments',
				'%"id":' . $attachment_id . '%'
			)
		);

		if ( $course_id ) {
			$post = get_post( $course_id );
			if ( $post && Course::POST_TYPE === $post->post_type ) {
				return [
					'type' => 'course',
					'id'   => (int) $course_id,
				];
			}
			if ( $post && Lesson::POST_TYPE === $post->post_type ) {
				return [
					'type' => 'lesson',
					'id'   => (int) $course_id,
				];
			}
		}

		return null;
	}

	/**
	 * Get access denied error with purchase info.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id   User ID.
	 * @return WP_Error
	 */
	private function get_access_error( int $course_id, int $user_id ): WP_Error {
		if ( ! $user_id ) {
			return new WP_Error(
				'unauthorized',
				__( 'Authentication required.', 'lw-lms' ),
				[ 'status' => 401 ]
			);
		}

		$access_info = AccessChecker::get_access_info( $course_id, $user_id );

		return new WP_Error(
			'forbidden',
			__( 'You do not have access to this content.', 'lw-lms' ),
			array_merge( [ 'status' => 403 ], $access_info )
		);
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
