<?php
/**
 * Attachment Meta Pattern.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api;

/**
 * The fragments that identify one attachment inside the serialized
 * `attachments` meta of a course or a lesson.
 *
 * WordPress stores that meta as a serialized PHP array, so an attachment
 * with id 8 appears as `s:2:"id";i:8;` — never as JSON. Matching the exact
 * serialized fragment also keeps the lookup from hitting an unrelated field
 * that happens to hold the same number.
 */
final class AttachmentMetaPattern {

	/**
	 * Fragments to look for, in the order they are most likely stored.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, string>
	 */
	public static function fragments( int $attachment_id ): array {
		$id = (string) $attachment_id;

		return [
			sprintf( 's:2:"id";i:%s;', $id ),
			sprintf( 's:2:"id";s:%d:"%s";', strlen( $id ), $id ),
		];
	}
}
