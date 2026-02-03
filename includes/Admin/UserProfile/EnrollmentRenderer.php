<?php
/**
 * Enrollment Renderer for user profile page.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\UserProfile;

use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\Options;

/**
 * Renders the course enrollment UI on user profile pages.
 */
final class EnrollmentRenderer {

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'lw_lms_enrollment';

	/**
	 * Nonce field name.
	 */
	public const NONCE_FIELD = '_lw_lms_enrollment_nonce';

	/**
	 * Render the full enrollment section.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function render_section( int $user_id ): void {
		$enrollments = \LightweightPlugins\LMS\Access\AccessRepository::get_user_enrollments( $user_id );
		$courses     = $this->get_available_courses( $enrollments );

		echo '<h2>' . esc_html__( 'Course Enrollment', 'lw-lms' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__( 'Manage course access for this user.', 'lw-lms' );
		echo '</p>';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$this->render_enrollment_table( $enrollments );
		$this->render_add_form( $courses );
	}

	/**
	 * Render the enrollment table.
	 *
	 * @param array<int, object> $enrollments Enrollment records.
	 * @return void
	 */
	private function render_enrollment_table( array $enrollments ): void {
		if ( empty( $enrollments ) ) {
			echo '<p><em>' . esc_html__( 'No active enrollments.', 'lw-lms' ) . '</em></p>';
			return;
		}

		echo '<table class="widefat lw-lms-enrollments-table">';
		$this->render_table_header();
		echo '<tbody>';

		foreach ( $enrollments as $enrollment ) {
			$this->render_enrollment_row( $enrollment );
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the table header.
	 *
	 * @return void
	 */
	private function render_table_header(): void {
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Course', 'lw-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'lw-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Granted', 'lw-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Expires', 'lw-lms' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'lw-lms' ) . '</th>';
		echo '</tr></thead>';
	}

	/**
	 * Render a single enrollment row.
	 *
	 * @param object $enrollment Enrollment record.
	 * @return void
	 */
	private function render_enrollment_row( object $enrollment ): void {
		$course_title = get_the_title( (int) $enrollment->course_id );
		$edit_link    = get_edit_post_link( (int) $enrollment->course_id );
		$expires      = $enrollment->expires_at
			? wp_date( get_option( 'date_format' ), strtotime( $enrollment->expires_at ) )
			: __( 'Lifetime', 'lw-lms' );

		echo '<tr>';
		echo '<td>';
		if ( $edit_link ) {
			echo '<a href="' . esc_url( $edit_link ) . '">';
			echo esc_html( $course_title );
			echo '</a>';
		} else {
			echo esc_html( $course_title );
		}
		echo '</td>';
		echo '<td>' . esc_html( ucfirst( $enrollment->source ) ) . '</td>';
		echo '<td>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $enrollment->granted_at ) ) ) . '</td>';
		echo '<td>' . esc_html( $expires ) . '</td>';
		echo '<td>';
		echo '<label>';
		echo '<input type="checkbox" name="lw_lms_revoke_courses[]" value="' . esc_attr( (string) $enrollment->course_id ) . '"> ';
		echo esc_html__( 'Revoke', 'lw-lms' );
		echo '</label>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render the add enrollment form.
	 *
	 * @param array<int, \WP_Post> $courses Available courses.
	 * @return void
	 */
	private function render_add_form( array $courses ): void {
		echo '<div class="lw-lms-add-enrollment">';
		echo '<h3>' . esc_html__( 'Add Enrollment', 'lw-lms' ) . '</h3>';

		if ( empty( $courses ) ) {
			echo '<p><em>';
			echo esc_html__( 'No paid courses available or user is already enrolled in all.', 'lw-lms' );
			echo '</em></p></div>';
			return;
		}

		echo '<div class="lw-lms-add-enrollment-fields">';

		// Course dropdown.
		echo '<label for="lw_lms_grant_course_id"><strong>';
		echo esc_html__( 'Course:', 'lw-lms' );
		echo '</strong></label> ';
		echo '<select name="lw_lms_grant_course_id" id="lw_lms_grant_course_id">';
		echo '<option value="">' . esc_html__( '-- Select Course --', 'lw-lms' ) . '</option>';
		foreach ( $courses as $course ) {
			echo '<option value="' . esc_attr( (string) $course->ID ) . '">';
			echo esc_html( $course->post_title );
			echo '</option>';
		}
		echo '</select>';

		// Expiry date.
		echo '<label for="lw_lms_grant_expires"><strong>';
		echo esc_html__( 'Expires:', 'lw-lms' );
		echo '</strong></label> ';
		echo '<input type="date" name="lw_lms_grant_expires" id="lw_lms_grant_expires">';
		echo '<span class="description">' . esc_html__( '(optional, empty = lifetime)', 'lw-lms' ) . '</span>';

		echo '</div></div>';
	}

	/**
	 * Get paid courses not yet enrolled.
	 *
	 * @param array<int, object> $enrollments Current enrollments.
	 * @return array<int, \WP_Post>
	 */
	private function get_available_courses( array $enrollments ): array {
		$enrolled_ids = array_map(
			static fn( object $e ): int => (int) $e->course_id,
			$enrollments
		);

		$args = [
			'post_type'      => Course::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_key'       => Options::META_PREFIX . 'access_type',
			'meta_value'     => 'paid',
		];

		if ( ! empty( $enrolled_ids ) ) {
			$args['post__not_in'] = $enrolled_ids;
		}

		return get_posts( $args );
	}
}
