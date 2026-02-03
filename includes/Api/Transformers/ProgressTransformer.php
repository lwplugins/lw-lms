<?php
/**
 * Progress Transformer.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Api\Transformers;

/**
 * Transforms progress data for API responses.
 */
final class ProgressTransformer {

	/**
	 * Transform a collection of progress entries.
	 *
	 * @param array $progress Progress entries.
	 * @return array
	 */
	public static function transform_collection( array $progress ): array {
		return array_map( [ self::class, 'transform_single' ], $progress );
	}

	/**
	 * Transform a single progress entry.
	 *
	 * @param object $entry Progress entry.
	 * @return array
	 */
	public static function transform_single( object $entry ): array {
		return [
			'user_id'      => (int) $entry->user_id,
			'course_id'    => (int) $entry->course_id,
			'lesson_id'    => (int) $entry->lesson_id,
			'status'       => $entry->status,
			'completed_at' => $entry->completed_at,
			'created_at'   => $entry->created_at,
			'updated_at'   => $entry->updated_at,
		];
	}
}
