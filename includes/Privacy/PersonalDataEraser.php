<?php
/**
 * Personal data eraser.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Privacy;

/**
 * Erases a user's LMS data for Tools → Erase Personal Data, and removes
 * every LMS row of a user who is deleted.
 *
 * An erasure request removes lesson progress, completion records, quiz
 * attempts (with their answers), quiz summaries, drip start dates and ended
 * enrollments. Active enrollments are kept by default, so the person keeps
 * access they were granted or paid for; the filter
 * `lw_lms_privacy_erase_active_enrollments` (return true) erases them too.
 */
final class PersonalDataEraser {

	/**
	 * Erase one user's data.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (everything goes in one pass).
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Eraser callback signature.
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return self::result( false, false, [] );
		}

		$user_id = (int) $user->ID;

		/**
		 * Whether an erasure request also removes active enrollments (and
		 * with them the user's access to those courses).
		 *
		 * @since 2.0.0
		 *
		 * @param bool $erase   Default false.
		 * @param int  $user_id User ID.
		 */
		$erase_active = (bool) apply_filters( 'lw_lms_privacy_erase_active_enrollments', false, $user_id );

		$removed  = PersonalDataQueries::delete_learning_data( $user_id );
		$removed += PersonalDataQueries::delete_access( $user_id, ! $erase_active );
		$retained = ! $erase_active && [] !== array_filter(
			PersonalDataQueries::access( $user_id ),
			static fn ( $row ): bool => 'active' === $row->status
		);

		$messages = $retained
			? [ __( 'Active course enrollments were kept so the user keeps access to those courses. Revoke them on the user profile, then erase again, to remove them.', 'lw-lms' ) ]
			: [];

		return self::result( $removed > 0, $retained, $messages );
	}

	/**
	 * Remove every LMS row of a deleted user (on every site of a network).
	 *
	 * @param int $user_id Deleted user ID.
	 * @return void
	 */
	public static function on_user_deleted( int $user_id ): void {
		if ( ! is_multisite() ) {
			self::delete_all( $user_id );
			return;
		}

		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::delete_all( $user_id );
			restore_current_blog();
		}
	}

	/**
	 * Delete every LMS row of a user on the current site.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function delete_all( int $user_id ): void {
		PersonalDataQueries::delete_learning_data( $user_id );
		PersonalDataQueries::delete_access( $user_id, false );
	}

	/**
	 * Eraser response.
	 *
	 * @param bool               $removed  Anything removed.
	 * @param bool               $retained Anything kept.
	 * @param array<int, string> $messages Messages.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	private static function result( bool $removed, bool $retained, array $messages ): array {
		return [
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		];
	}
}
