<?php
/**
 * Permission checks on the values written into LMS meta.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Meta;

use LightweightPlugins\LMS\Api\AttachmentOwners;

/**
 * Whether the saving user may reference a post (course, attachment) in meta.
 *
 * MetaAuth decides who may write a key at all; this decides which IDs the
 * written value may point at. A registered sanitize_callback does not get the
 * ID of the post being saved, so the rules only look at the referenced post.
 * WP-CLI runs without a user and is trusted: no check there.
 */
final class MetaPermission {

	/**
	 * Whether the current user may edit the post (always true on WP-CLI).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_edit( int $post_id ): bool {
		return self::is_cli() || current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Whether the current user may list the attachment on a course or lesson.
	 *
	 * Allowed when the user may edit the attachment itself, or when a course
	 * or lesson the user may edit already lists it (so re-saving a course
	 * keeps files an administrator added to it).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function can_attach( int $attachment_id ): bool {
		if ( self::can_edit( $attachment_id ) ) {
			return true;
		}

		foreach ( AttachmentOwners::find( $attachment_id ) as $owner_id ) {
			if ( current_user_can( 'edit_post', $owner_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the request runs on WP-CLI.
	 *
	 * @return bool
	 */
	private static function is_cli(): bool {
		return defined( 'WP_CLI' ) && \WP_CLI;
	}
}
