<?php
/**
 * Who may see and search learners' email addresses in the LMS admin.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Privacy;

/**
 * Email addresses are shown, and matched by the learner search, only for
 * users who may list users in wp-admin (list_users). Everyone else with
 * manage_lms sees the display name and login; a substring search on the
 * email column would otherwise work as an email oracle.
 */
final class EmailVisibility {

	/**
	 * Filter key the admin list queries read to include the email column
	 * in the learner search.
	 */
	public const SEARCH_FLAG = 'search_email';

	/**
	 * Whether the current user may see and search email addresses.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( 'list_users' );
	}

	/**
	 * Learner search clause on the users table aliased "u".
	 *
	 * @param string $search_like LIKE-escaped search text (not empty).
	 * @param bool   $with_email  Also match user_email.
	 * @return array{0: string, 1: array<int, string>} SQL with placeholders, arguments.
	 */
	public static function search_clause( string $search_like, bool $with_email ): array {
		$columns = $with_email
			? [ 'u.user_login', 'u.user_email', 'u.display_name' ]
			: [ 'u.user_login', 'u.display_name' ];
		$like    = '%' . $search_like . '%';

		return [
			'(' . implode( ' OR ', array_map( static fn ( string $c ): string => $c . ' LIKE %s', $columns ) ) . ')',
			array_fill( 0, count( $columns ), $like ),
		];
	}
}
