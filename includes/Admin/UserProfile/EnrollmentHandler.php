<?php
/**
 * Enrollment Handler for user profile save.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\UserProfile;

use LightweightPlugins\LMS\Access\AccessRepository;

/**
 * Handles enrollment save operations from user profile.
 */
final class EnrollmentHandler {

	/**
	 * Handle save from user profile page.
	 *
	 * @param int $user_id User ID being edited.
	 * @return void
	 */
	public function handle_save( int $user_id ): void {
		if ( ! isset( $_POST[ EnrollmentRenderer::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ EnrollmentRenderer::NONCE_FIELD ] ) ),
			EnrollmentRenderer::NONCE_ACTION
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_lms' ) ) {
			return;
		}

		$this->process_revocations( $user_id );
		$this->process_grant( $user_id );
	}

	/**
	 * Process course revocations.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function process_revocations( int $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
		if ( empty( $_POST['lw_lms_revoke_courses'] ) || ! is_array( $_POST['lw_lms_revoke_courses'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
		$course_ids = array_map( 'absint', $_POST['lw_lms_revoke_courses'] );

		foreach ( $course_ids as $course_id ) {
			if ( $course_id > 0 ) {
				AccessRepository::revoke( $user_id, $course_id );
			}
		}
	}

	/**
	 * Process new enrollment grant.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function process_grant( int $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
		if ( empty( $_POST['lw_lms_grant_course_id'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
		$course_id = absint( $_POST['lw_lms_grant_course_id'] );

		if ( 0 === $course_id ) {
			return;
		}

		$expires_at = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
		if ( ! empty( $_POST['lw_lms_grant_expires'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_save().
			$date = sanitize_text_field( wp_unslash( $_POST['lw_lms_grant_expires'] ) );

			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$expires_at = $date . ' 23:59:59';
			}
		}

		AccessRepository::grant( $user_id, $course_id, 'manual', null, $expires_at );
	}
}
