<?php
/**
 * Validates a manual enrollment grant.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Admin\Enrollments;

use LightweightPlugins\LMS\Admin\UserProfile\EnrollmentHandler;
use LightweightPlugins\LMS\Api\Admin\ListParams;
use LightweightPlugins\LMS\PostTypes\Course;

/**
 * Body { user_id, course_id, expires? }. `expires` is a Y-m-d date in site
 * time (access ends at the end of that day), today or later; empty or null
 * means lifetime access.
 */
final class GrantInput {

	/**
	 * Field errors: key => messages.
	 *
	 * @var array<string, array<int, string>>
	 */
	public array $errors = [];

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public int $user_id = 0;

	/**
	 * Course ID.
	 *
	 * @var int
	 */
	public int $course_id = 0;

	/**
	 * UTC expiry, or null for lifetime access.
	 *
	 * @var string|null
	 */
	public ?string $expires_at = null;

	/**
	 * Validate a body.
	 *
	 * @param array<array-key, mixed> $body  Decoded JSON body.
	 * @param string                  $today Today in site time, Y-m-d.
	 */
	public function __construct( array $body, string $today ) {
		$this->user_id   = self::id( $body['user_id'] ?? null );
		$this->course_id = self::id( $body['course_id'] ?? null );

		if ( 0 === $this->user_id || ! get_userdata( $this->user_id ) ) {
			$this->errors['user_id'][] = __( 'Choose an existing user.', 'lw-lms' );
		}

		$course = $this->course_id > 0 ? get_post( $this->course_id ) : null;

		if ( ! $course || Course::POST_TYPE !== $course->post_type || 'trash' === $course->post_status ) {
			$this->errors['course_id'][] = __( 'Choose an existing course.', 'lw-lms' );
		}

		$this->parse_expiry( $body['expires'] ?? null, $today );
	}

	/**
	 * A positive integer ID, or 0.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function id( mixed $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		return is_string( $value ) && 1 === preg_match( '/^\d{1,19}$/', $value ) ? (int) $value : 0;
	}

	/**
	 * Expiry date: empty = lifetime; otherwise a real date, today or later.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $today Today in site time, Y-m-d.
	 * @return void
	 */
	private function parse_expiry( mixed $value, string $today ): void {
		if ( null === $value || '' === $value ) {
			return;
		}

		$date = is_string( $value ) ? ListParams::date( trim( $value ) ) : null;

		if ( null === $date ) {
			$this->errors['expires'][] = __( 'Enter a date like 2026-12-31, or leave it empty for lifetime access.', 'lw-lms' );
			return;
		}

		if ( $date < $today ) {
			$this->errors['expires'][] = __( 'The end date cannot be in the past.', 'lw-lms' );
			return;
		}

		$this->expires_at = EnrollmentHandler::expiry_from_date( $date );
	}
}
