<?php
/**
 * Signed download links.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

/**
 * Builds and verifies the short-lived signed URLs of GET /lms/v1/download/{id}.
 *
 * A plain `<a href>` to a REST route carries the login cookie but no REST
 * nonce, and core then treats the request as a guest (rest_cookie_check_errors),
 * so a learner's own download link answered 401. A REST nonce in the URL would
 * only help when the page and the API share the same cookie session, which a
 * headless frontend does not guarantee.
 *
 * The link therefore carries its own proof: the attachment ID, the user ID it
 * was issued to and an expiry, signed with an HMAC keyed by the site's secret
 * salt. The endpoint trusts the signed user ID instead of the (missing) REST
 * authentication and still runs the full access check for that user when the
 * file is requested, so revoking access also stops links already handed out.
 * Links expire after an hour by default (filter `lw_lms_download_link_ttl`).
 */
final class DownloadLink {

	/**
	 * Default lifetime of a link, in seconds.
	 */
	public const DEFAULT_TTL = 3600;

	/**
	 * Query argument names.
	 */
	public const ARG_USER      = 'lw_user';
	public const ARG_EXPIRES   = 'lw_expires';
	public const ARG_SIGNATURE = 'lw_signature';

	/**
	 * Signed download URL of an attachment for a user.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param int      $user_id       User the link is issued to (0 = guest).
	 * @param int|null $now           Current Unix time (null = time()).
	 * @return string
	 */
	public static function url( int $attachment_id, int $user_id, ?int $now = null ): string {
		$expires = ( $now ?? time() ) + self::ttl();

		return add_query_arg(
			[
				self::ARG_USER      => $user_id,
				self::ARG_EXPIRES   => $expires,
				self::ARG_SIGNATURE => self::sign( $attachment_id, $user_id, $expires ),
			],
			rest_url( RestApi::NAMESPACE . '/download/' . $attachment_id )
		);
	}

	/**
	 * Whether a signature is valid and not expired.
	 *
	 * @param int      $attachment_id Attachment ID from the route.
	 * @param int      $user_id       Signed user ID.
	 * @param int      $expires       Signed expiry (Unix time).
	 * @param string   $signature     Signature from the URL.
	 * @param int|null $now           Current Unix time (null = time()).
	 * @return bool
	 */
	public static function verify( int $attachment_id, int $user_id, int $expires, string $signature, ?int $now = null ): bool {
		if ( $expires < ( $now ?? time() ) || '' === $signature ) {
			return false;
		}

		return hash_equals( self::sign( $attachment_id, $user_id, $expires ), $signature );
	}

	/**
	 * HMAC of the link fields.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       User ID.
	 * @param int $expires       Expiry (Unix time).
	 * @return string
	 */
	private static function sign( int $attachment_id, int $user_id, int $expires ): string {
		return hash_hmac(
			'sha256',
			sprintf( 'lw-lms-download|%d|%d|%d', $attachment_id, $user_id, $expires ),
			wp_salt( 'auth' )
		);
	}

	/**
	 * Link lifetime in seconds (at least one minute).
	 *
	 * @return int
	 */
	private static function ttl(): int {
		/**
		 * Filter how long a signed download link stays valid, in seconds.
		 *
		 * @since 2.0.0
		 *
		 * @param int $ttl Lifetime in seconds. Default 3600.
		 */
		return max( 60, (int) apply_filters( 'lw_lms_download_link_ttl', self::DEFAULT_TTL ) );
	}
}
