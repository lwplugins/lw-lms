<?php
/**
 * Meta write permission.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

/**
 * The auth_callback of every course and lesson meta key.
 */
final class MetaAuth {

	/**
	 * Whether the user may change LMS meta of this post: edit_post on it.
	 *
	 * Replaces the blanket edit_posts check, which let a Contributor write
	 * the meta of any course or lesson through core REST.
	 *
	 * @param bool   $allowed  Whether allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit( $allowed = false, $meta_key = '', $post_id = 0, $user_id = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- auth_callback signature.
		$post_id = (int) $post_id;
		$user_id = (int) $user_id;

		if ( $post_id <= 0 ) {
			return current_user_can( 'edit_posts' );
		}

		return $user_id > 0 ? user_can( $user_id, 'edit_post', $post_id ) : current_user_can( 'edit_post', $post_id );
	}
}
