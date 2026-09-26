<?php
/**
 * Rejected quiz submissions.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin\Quiz;

/**
 * Keeps the last rejected quiz JSON of a lesson and why it was rejected, for
 * ten minutes, so the editor sees the error and does not lose their work.
 */
final class QuizSaveError {

	/**
	 * Transient prefix.
	 */
	private const TRANSIENT = 'lw_lms_quiz_error_';

	/**
	 * Store a rejection.
	 *
	 * @param int    $lesson_id Lesson ID.
	 * @param string $json      Submitted JSON.
	 * @param string $message   Validation message.
	 * @return void
	 */
	public static function set( int $lesson_id, string $json, string $message ): void {
		set_transient(
			self::TRANSIENT . $lesson_id,
			[
				'json'    => $json,
				'message' => $message,
			],
			MINUTE_IN_SECONDS * 10
		);
	}

	/**
	 * The stored rejection, or null.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array{json: string, message: string}|null
	 */
	public static function get( int $lesson_id ): ?array {
		$rejected = get_transient( self::TRANSIENT . $lesson_id );

		if ( ! is_array( $rejected ) ) {
			return null;
		}

		return [
			'json'    => (string) ( $rejected['json'] ?? '' ),
			'message' => (string) ( $rejected['message'] ?? '' ),
		];
	}

	/**
	 * Forget the rejection (after a successful save, or once shown).
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return void
	 */
	public static function clear( int $lesson_id ): void {
		delete_transient( self::TRANSIENT . $lesson_id );
	}
}
