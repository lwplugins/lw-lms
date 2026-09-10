<?php
/**
 * Staff access bypass.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Access;

use LightweightPlugins\LMS\Options;

/**
 * Decides whether a user reaches every course through the opt-in
 * "auto-enroll administrators" setting.
 *
 * A pure runtime check: it writes no access row, fires no lw_lms_after_grant
 * and leaves nothing to revoke when the setting is switched off.
 */
final class AdminAccess {

	/**
	 * Option key of the setting (in lw_lms_options).
	 */
	public const OPTION_KEY = 'auto_enroll_admins';

	/**
	 * Capability that unlocks every course unless filtered.
	 */
	public const DEFAULT_CAPABILITY = 'manage_lms';

	/**
	 * Whether the bypass grants this user access to every course.
	 *
	 * @param int $user_id User ID (0 = guest).
	 * @return bool
	 */
	public static function applies( int $user_id ): bool {
		if ( ! $user_id || ! Options::get( self::OPTION_KEY, false ) ) {
			return false;
		}

		/**
		 * Filter the capability that unlocks every course when the
		 * "auto-enroll administrators" setting is on.
		 *
		 * @since 1.7.0
		 *
		 * @param string $capability Capability name. Default 'manage_lms'.
		 */
		$capability = (string) apply_filters( 'lw_lms_admin_access_capability', self::DEFAULT_CAPABILITY );

		return user_can( $user_id, $capability );
	}
}
