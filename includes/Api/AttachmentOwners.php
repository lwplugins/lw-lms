<?php
/**
 * Attachment owners lookup.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

use LightweightPlugins\LMS\Options;
use LightweightPlugins\LMS\PostTypes\Course;
use LightweightPlugins\LMS\PostTypes\Lesson;

/**
 * Finds every course and lesson whose `attachments` meta lists a file.
 *
 * Returns all owners in ascending post ID order, so the download decision
 * no longer depends on which row the database happens to return first when a
 * file is attached to more than one course or lesson.
 */
final class AttachmentOwners {

	/**
	 * Course and lesson IDs that list the attachment, ascending.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, int>
	 */
	public static function find( int $attachment_id ): array {
		global $wpdb;

		$fragments = AttachmentMetaPattern::fragments( $attachment_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reverse lookup by serialized meta fragment; no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type IN ( %s, %s )
				AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s )
				ORDER BY pm.post_id ASC",
				Options::META_PREFIX . 'attachments',
				Course::POST_TYPE,
				Lesson::POST_TYPE,
				'%' . $wpdb->esc_like( $fragments[0] ) . '%',
				'%' . $wpdb->esc_like( $fragments[1] ) . '%'
			)
		);

		$owners = [];

		foreach ( (array) $rows as $row ) {
			if ( self::lists( maybe_unserialize( $row->meta_value ), $attachment_id ) ) {
				$owners[] = (int) $row->post_id;
			}
		}

		return array_values( array_unique( $owners ) );
	}

	/**
	 * Whether a decoded attachments list contains the attachment ID.
	 *
	 * The LIKE prefilter is a fragment match; this confirms it against the
	 * decoded structure.
	 *
	 * @param mixed $attachments Decoded meta value.
	 * @param int   $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function lists( mixed $attachments, int $attachment_id ): bool {
		if ( ! is_array( $attachments ) ) {
			return false;
		}

		foreach ( $attachments as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) && is_numeric( $item['id'] ) && (int) $item['id'] === $attachment_id ) {
				return true;
			}
		}

		return false;
	}
}
